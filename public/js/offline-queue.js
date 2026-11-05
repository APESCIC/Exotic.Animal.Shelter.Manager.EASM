/*
 * Offline queue for forms marked data-offline-queue.
 *
 * Every submit gets a client request id. Without signal the form is stored in
 * IndexedDB and replayed when the phone is back online; the server applies each id
 * at most once (DeduplicateClientRequests), so a replay never saves anything twice.
 */
(function () {
    'use strict';

    var DB_NAME = 'easm-offline';
    var STORE = 'queue';
    var RETRY_MS = 60000;
    var draining = false;

    function body() {
        return document.body;
    }

    function userId() {
        return body() ? body().getAttribute('data-user-id') : null;
    }

    function requestId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        var bytes = new Uint8Array(16);
        window.crypto.getRandomValues(bytes);
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        var hex = Array.prototype.map.call(bytes, function (b) {
            return ('0' + b.toString(16)).slice(-2);
        }).join('');
        return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
    }

    function openDb() {
        return new Promise(function (resolve, reject) {
            if (!window.indexedDB) {
                reject(new Error('IndexedDB is not available'));
                return;
            }
            var request = window.indexedDB.open(DB_NAME, 1);
            request.onupgradeneeded = function () {
                request.result.createObjectStore(STORE, { keyPath: 'id' });
            };
            request.onsuccess = function () {
                resolve(request.result);
            };
            request.onerror = function () {
                reject(request.error);
            };
        });
    }

    function withStore(mode, work) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(STORE, mode);
                var result = work(tx.objectStore(STORE));
                tx.oncomplete = function () {
                    resolve(result && result.result !== undefined ? result.result : undefined);
                };
                tx.onerror = function () {
                    reject(tx.error);
                };
            });
        });
    }

    function allItems() {
        return withStore('readonly', function (store) {
            return store.getAll();
        }).then(function (items) {
            return (items || []).sort(function (a, b) {
                return a.createdAt - b.createdAt;
            });
        });
    }

    function putItem(item) {
        return withStore('readwrite', function (store) {
            store.put(item);
        });
    }

    function deleteItem(id) {
        return withStore('readwrite', function (store) {
            store.delete(id);
        });
    }

    function pad(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function localDate(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }

    /* Record when the work happened, not when the queue is replayed. */
    function stamp(form) {
        var now = new Date();
        form.querySelectorAll('[data-offline-today]').forEach(function (input) {
            input.value = localDate(now);
        });
        form.querySelectorAll('[data-offline-now]').forEach(function (input) {
            input.value = localDate(now) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
        });
    }

    function formEntries(form) {
        var entries = [];
        new FormData(form).forEach(function (value, key) {
            if (key === '_token') {
                return;
            }
            if (value instanceof File && value.size === 0 && value.name === '') {
                return;
            }
            entries.push([key, value]);
        });
        return entries;
    }

    function pageToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function freshToken() {
        var url = body() ? body().getAttribute('data-token-url') : null;
        if (!url) {
            return Promise.resolve(null);
        }
        return fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store',
        }).then(function (response) {
            if (response.status === 401) {
                return { signedOut: true };
            }
            if (!response.ok) {
                throw new Error('Token request failed');
            }
            return response.json();
        });
    }

    function firstError(data) {
        if (data && data.errors) {
            var keys = Object.keys(data.errors);
            if (keys.length && data.errors[keys[0]].length) {
                return data.errors[keys[0]][0];
            }
        }
        return data && data.message ? data.message : 'The server did not accept this.';
    }

    /* Resolves to { ok, retry, message, signedOut }. */
    function send(item, token) {
        var data = new FormData();
        item.entries.forEach(function (entry) {
            if (entry[1] instanceof Blob) {
                data.append(entry[0], entry[1], entry[1].name || 'upload');
            } else {
                data.append(entry[0], entry[1]);
            }
        });
        data.append('_client_request_id', item.id);

        return fetch(item.url, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            redirect: 'manual',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token,
                'X-Client-Request-Id': item.id,
            },
        }).then(function (response) {
            if (response.type === 'opaqueredirect') {
                return { ok: true };
            }
            if (response.ok) {
                return response.json().catch(function () {
                    return {};
                }).then(function (json) {
                    return { ok: true, message: json.message };
                });
            }
            if (response.status === 401) {
                return { ok: false, retry: true, signedOut: true };
            }
            if (response.status === 419 || response.status === 429 || response.status >= 500) {
                return { ok: false, retry: true };
            }
            return response.json().catch(function () {
                return {};
            }).then(function (json) {
                return { ok: false, retry: false, message: firstError(json) };
            });
        }, function () {
            return { ok: false, retry: true };
        });
    }

    function render(items, note) {
        var panel = document.querySelector('[data-offline-status]');
        if (!panel) {
            return;
        }
        var mine = items.filter(function (item) {
            return item.userId === userId();
        });
        var pending = mine.filter(function (item) {
            return !item.error;
        });
        var failed = mine.filter(function (item) {
            return item.error;
        });
        var others = items.length - mine.length;

        panel.textContent = '';
        if (!pending.length && !failed.length && !others && !note) {
            panel.hidden = true;
            return;
        }
        panel.hidden = false;

        var summary = document.createElement('p');
        var parts = [];
        if (!navigator.onLine) {
            parts.push('You are offline.');
        }
        if (pending.length) {
            parts.push(pending.length + (pending.length === 1 ? ' change is' : ' changes are') + ' saved on this phone and will be sent when you are back online.');
        }
        if (others) {
            parts.push(others + ' saved ' + (others === 1 ? 'change belongs' : 'changes belong') + ' to someone else; they will be sent when that person signs in on this phone.');
        }
        if (note) {
            parts.push(note);
        }
        summary.textContent = parts.join(' ');
        panel.appendChild(summary);

        if (failed.length) {
            var list = document.createElement('ul');
            failed.forEach(function (item) {
                var li = document.createElement('li');
                li.textContent = (item.label || 'Saved change') + ' was not accepted: ' + item.error + ' ';
                var discard = document.createElement('button');
                discard.type = 'button';
                discard.className = 'linkish';
                discard.textContent = 'Discard';
                discard.addEventListener('click', function () {
                    deleteItem(item.id).then(refresh);
                });
                li.appendChild(discard);
                list.appendChild(li);
            });
            panel.appendChild(list);
        }
    }

    function refresh(note) {
        return allItems().then(function (items) {
            render(items, typeof note === 'string' ? note : '');
        }, function () {});
    }

    function drain() {
        if (draining || !navigator.onLine || !userId()) {
            return refresh();
        }
        draining = true;

        return allItems().then(function (items) {
            var queue = items.filter(function (item) {
                return item.userId === userId() && !item.error;
            });
            if (!queue.length) {
                return '';
            }
            return freshToken().then(function (data) {
                if (!data || data.signedOut) {
                    return 'Sign in to send them.';
                }
                var sent = 0;
                var chain = Promise.resolve(false);
                queue.forEach(function (item) {
                    chain = chain.then(function (stop) {
                        if (stop) {
                            return true;
                        }
                        return send(item, data.token).then(function (result) {
                            if (result.ok) {
                                sent++;
                                return deleteItem(item.id).then(function () {
                                    return false;
                                });
                            }
                            if (result.retry) {
                                return true;
                            }
                            item.error = result.message;
                            return putItem(item).then(function () {
                                return false;
                            });
                        });
                    });
                });
                return chain.then(function () {
                    return sent ? sent + (sent === 1 ? ' saved change was' : ' saved changes were') + ' sent.' : '';
                });
            });
        }).catch(function () {
            return '';
        }).then(function (note) {
            draining = false;
            return refresh(note);
        });
    }

    function flash(form, text) {
        var target = form.closest('[data-offline-item]') || form;
        var message = document.createElement('p');
        message.className = 'offline-flash';
        message.setAttribute('role', 'status');
        message.textContent = text;
        target.parentNode.insertBefore(message, target.nextSibling);
        setTimeout(function () {
            message.remove();
        }, 6000);
    }

    function finish(form, text) {
        var mode = form.getAttribute('data-offline-done');
        var item = form.closest('[data-offline-item]');
        if (mode === 'remove' && item) {
            flash(form, text);
            item.remove();
        } else {
            form.reset();
            flash(form, text);
        }
    }

    function initForm(form) {
        form.addEventListener('submit', function (event) {
            if (!window.indexedDB || !window.fetch || !userId()) {
                return;
            }
            event.preventDefault();
            if (form.getAttribute('data-busy') === '1') {
                return;
            }
            form.setAttribute('data-busy', '1');
            stamp(form);

            var item = {
                id: requestId(),
                url: form.action,
                entries: formEntries(form),
                label: form.getAttribute('data-offline-label') || '',
                userId: userId(),
                createdAt: Date.now(),
            };

            var queue = function () {
                return putItem(item).then(function () {
                    finish(form, 'Saved on this phone. It will be sent when you are back online.');
                    return refresh();
                });
            };

            var attempt = navigator.onLine
                ? send(item, pageToken()).then(function (result) {
                    if (result.ok) {
                        finish(form, result.message || 'Saved.');
                        return null;
                    }
                    if (result.retry) {
                        return queue();
                    }
                    flash(form, result.message);
                    return null;
                })
                : queue();

            attempt.catch(function () {
                flash(form, 'Could not save this. Try again.');
            }).then(function () {
                form.removeAttribute('data-busy');
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-offline-queue]').forEach(initForm);
        window.addEventListener('online', drain);
        window.addEventListener('offline', function () {
            refresh();
        });
        setInterval(drain, RETRY_MS);
        drain();
    });
})();
