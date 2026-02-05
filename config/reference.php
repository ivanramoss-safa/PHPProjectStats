<?php


namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Config\Loader\ParamConfigurator as Param;


final class App
{
    
    public static function config(array $config): array
    {
        return AppReference::config($config);
    }
}

namespace Symfony\Component\Routing\Loader\Configurator;


final class Routes
{
    
    public static function config(array $config): array
    {
        return $config;
    }
}
