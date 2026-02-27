<?php

return array_filter([
    App\Providers\AppServiceProvider::class,
    App\Providers\DrumRackAnalyzerServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\JetstreamServiceProvider::class,
    App\Providers\MimeTypeServiceProvider::class,
    class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)
        ? App\Providers\TelescopeServiceProvider::class
        : null,
]);
