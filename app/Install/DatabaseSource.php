<?php

namespace App\Install;

enum DatabaseSource: string
{
    /** The host writes DB_* itself (EASM_MANAGED_DATABASE=true). */
    case Managed = 'managed';

    /** Cloudron's MySQL addon, read from CLOUDRON_MYSQL_* on every request. */
    case Cloudron = 'cloudron';

    /** Credentials entered in the wizard and saved as DB_* in .env. */
    case Custom = 'custom';
}
