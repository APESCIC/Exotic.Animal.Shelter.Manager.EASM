<?php

/**
 * Root bootstrap for hosts that cannot point the document root at public/.
 *
 * Prefer a document root of public/. This file only exists so shared hosting
 * that is stuck on the repository root can still reach the front controller.
 */
require __DIR__.'/public/index.php';
