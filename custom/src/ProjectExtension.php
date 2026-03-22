<?php

namespace custom;

class ProjectExtension {

    public static function boot(): void {
    }

    public static function afterGetStandardViews(...$args): void {
        unset($args);
    }
}
