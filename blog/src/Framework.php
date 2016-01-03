<?php

require(__DIR__.'/BaseFramework.php');

class Framework extends \framework\BaseFramework
{
}

spl_autoload_register(['Framework', 'autoload'], true, true);
Framework::$classMap = require(__DIR__.'/classes.php');
Framework::$container = new framework\di\Container();