<?php

declare(strict_types=1);

namespace App\CMS;

final class Version
{
    /** Current CMS release. Bump when cutting a new version. */
    public const APP = '0.1.4b';

    /** Phuse framework version this CMS was built on. */
    public const PHUSE = '1.3.0';
}
