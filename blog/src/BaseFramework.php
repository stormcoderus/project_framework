<?php

namespace framework;

use framework\base\InvalidConfigException;
use framework\base\InvalidParamException;
use framework\base\UnknownClassException;
use framework\log\Logger;
use framework\di\Container;

/**
 * Gets the application start timestamp.
 */
defined('FRAMEWORK_BEGIN_TIME') or define('FRAMEWORK_BEGIN_TIME', microtime(true));
/**
 * This constant defines the framework installation directory.
 */
defined('FRAMEWORK_PATH') or define('FRAMEWORK_PATH', __DIR__);
/**
 * This constant defines whether the application should be in debug mode or not. Defaults to false.
 */
defined('FRAMEWORK_DEBUG') or define('FRAMEWORK_DEBUG', false);
/**
 * This constant defines in which environment the application is running. Defaults to 'prod', meaning production environment.
 * You may define this constant in the bootstrap script. The value could be 'prod' (production), 'dev' (development), 'test', 'staging', etc.
 */
defined('FRAMEWORK_ENV') or define('FRAMEWORK_ENV', 'prod');
/**
 * Whether the the application is running in production environment
 */
defined('FRAMEWORK_ENV_PROD') or define('FRAMEWORK_ENV_PROD', YII_ENV === 'prod');
/**
 * Whether the the application is running in development environment
 */
defined('FRAMEWORK_ENV_DEV') or define('FRAMEWORK_ENV_DEV', YII_ENV === 'dev');
/**
 * Whether the the application is running in testing environment
 */
defined('FRAMEWORK_ENV_TEST') or define('FRAMEWORK_ENV_TEST', YII_ENV === 'test');

/**
 * This constant defines whether error handling should be enabled. Defaults to true.
 */
defined('FRAMEWORK_ENABLE_ERROR_HANDLER') or 
	define('FRAMEWORK_ENABLE_ERROR_HANDLER', true);

class BaseFramework
{
	public static $classMap = [];

	public static $app;

	public static $aliases = ['@framework' => __DIR__];

	public static $container;

	public static function getVersion()
	{
		return '0.0.1_Alpha';
	}

	public static function getAlias($alias, $throwException = true)
	{
		if  (strncmp($alias, '@', 1)) {
			return $alias;
		}

		$pos = strpos($alias, '/');
		$root = $pos === false ? $alias : substr($alias, 0, $pos);

		if (isset(static::$aliases[$root])) 
		{
			if (is_string(static::$aliases[$root])) {
				return $pos === false ? static::$aliases[$root] : 
					   static::$aliases[$root] . substr($alias, $pos);
			}
			else {
				foreach (static::$aliases[$root] as $name => $path) {
					if (strpos($alias . '/', $name . '/') === 0) {
						return $path . substr($alias, strlen($name));
					}
				}
			}
		}

		if ($throwException) {
			throw new InvalidParamException("Invalid path alias: $alias");
		} else {
			return false;
		}
	}

	public static function getRootAlias($alias)
	{
		$pos = strpos($alias, '/');
		$root = $pos === false ? $alias : substr($alias, 0, $pos);

		if (isset(static::$aliases[$root]))
		{
			if (is_string(static::$aliases[$root])) {
				return $root;
			}
			else {
				foreach (static::$aliases[$root] as $name => $path) {
					if (strpos($alias . '/', $name . '/') === 0) {
						return $name;
					}
				}
			}
		}

		return false;
	}

	public static function setAlias($alias, $path)
	{
		if (strncmp($alias, '@', 1)) {
			$alias = '@' . $alias;
		}
		$pos = strpos($alias, '/');
		$root = $pos === false ? $alias : substr($alias, 0, $pos);

		if ($path !== null)
		{
			$path = strncmp($path, '@', 1) ? rtrim($path, '\\/') : 
											 static::getAlias($path);
			if (!isset(static::$aliases[$root])) {
				if ($pos === false) {
					static::$aliases[$root] = $path;
				} else {
					static::$aliases[$root] = [$alias => $path];
				}
			}
			elseif (is_string(static::$aliases[$root])) {
				if ($pos === false) {
					static::$aliases[$root] = $path;
				}
				else {
					static::$aliases[$root] = [
						$alias => $path,
						$root => static::$aliases[$root],
					];
				}
			}
			else {
				static::$aliases[$root][$alias] = $path;
				krsort(static::$aliases[$root]);
			}
		}
		elseif (isset(static::$aliases[$root])) 
		{
			if (is_array(static::$aliases[$root])) {
				unset(static::$aliases[$root][$alias]);
			} elseif ($pos === false) {
				unset(static::$aliases[$root]);
			}
		}
	}

	public static function autoload($className)
	{
		if (isset(static::$classMap{$className]))
		{
			$classFile = static::$classMap[$className];
			if ($classFile[0] === '@') {
				$classFile = static::getAlias($classFile);
			}
		}
		elseif (strpos($className, '\\') !== false) 
		{
			$classFile = static::getAlias('@' . 
				str_replace('\\', '/', $className)) . 'php', false);
			if ($classFile === false || !is_file($classFile)) {
				return;
			}
		}
		else {
			return;
		}

		include ($classFile);

		if (FRAMEWORK_DEBUG && !class_exists($className, false) && 
			!interface_exists($className, false) && !
			trait_exists($className, false))
		{
			throw new UnknownClassException("Unable to find '$className' in 
				file: $classFile. Namespace missing?");
		}
	}

	public static function createObject($type, array $params = [])
	{
		if (is_string($type)) {
			return static::$container->get($type, $params);
		} elseif (is_array($type) && isset($type['class'])) {
			$class = $type['class'];
			unset($type['class']);
			return static::$container->get($class, $params, $type);
		} elseif (is_callable($type, true)) {
			return call_user_func($type, $params);
		} elseif (is_array($type)) {
			throw new InvalidConfigException('Object configuration
				must be an array containing a "class" element.');
		} else {
			throw new InvalidConfigException("Unsupported configuration type: " 
				. gettype($type));
		}
	}

	private static $_loger;

	public static function getLogger()
	{
		if (self::$_logger !== null) {
			return self::$_logger;
		} else {
			return self::$_logger = static::createObject('framework\log\Logger');
		}
	}

	private static function setLogger($logger)
	{
		self::$_logger = $logger;
	}

	public static function trace($message, $category = 'application')
	{
		if (FRAMEWORK_DEBUG) {
			static::getLogger()->log($message, Logger::LEVEL_TRACE, $category);
		}
	}

	public static function error($message, $category = 'application')
	{
		static::getLogger()->log($message, Logger::LEVEL_ERROR, $category);
	}

	public static function warning($message, $category = 'application')
	{
		static::getLogger()->log($message, Logger::LEVEL_WARNING, $category);
	}

	public static function info($message, $category = 'application')
	{
		static::getLogger()->log($message, Logger::LEVEL_INFO, $category);
	}

	public static function beginProfile($token, $category = 'application')
	{
		static::getLogger()->log($token, Logger::LEVEL_PROFILE_BEGIN, $category);
	}

	public static function endProfile($token, $category = 'application')
	{
		static::getLogger()->log($token, Logger::LEVEL_PROFILE_END, $category);
	}

	public static function powered()
	{
		return 'Powered by <a href="" rel="externa">Framework</a>';
	}

	public static function t($category, $message, $params = [], $language = null)
	{
		if (static::$app !== null) {
			return static::$app->getI18n()->translate($category, $message, 
				$params, $language ?: static::$app->language);
		} else {
			$p = [];
			foreach ((array) $params as $name =>value) {
				$p['{' . $name . '}'] = $value;
			}

			return ($p === []) ? $message : strstr($message, $p);
		}
	}

	public static function configure($object, $properties)
	{
		foreach ($properties as $name => $value) {
			$object->$name = $value;
		}

		return $object;
	}

	public static function getObjectVars($object)
    {
        return get_object_vars($object);
    }
}
}