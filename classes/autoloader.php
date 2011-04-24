<?php
/**
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.0
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2011 Fuel Development Team
 * @link       http://fuelphp.com
 */

/**
 * The Autloader is responsible for all class loading.  It allows you to define
 * different load paths based on namespaces.  It also lets you set explicit paths
 * for classes to be loaded from.
 *
 * @package     Fuel
 * @subpackage  Core
 */
class Autoloader {

	/**
	 * @var  array  $classes  holds all the classes and paths
	 */
	protected static $classes = array();

	/**
	 * @var  array  holds all the namespace paths
	 */
	protected static $namespaces = array();

	/**
	 * @var  array  list off namespaces of which classes will be aliased to global namespace
	 */
	protected static $core_namespaces = array('Fuel\\Core');

	/**
	 * @var  array  the default path to look in if the class is not in a package
	 */
	protected static $default_path = null;

	/**
	 * @var  bool  whether to initialize a loaded class
	 */
	protected static $auto_initialize = null;

	/**
	 * Adds a namespace search path.  Any class in the given namespace will be
	 * looked for in the given path.
	 *
	 * @param   string  the namespace
	 * @param   string  the path
	 * @return  void
	 */
	public static function add_namespace($namespace, $path)
	{
		static::$namespaces[$namespace] = $path;
	}

	/**
	 * Adds an array of namespace paths. See {add_namespace}.
	 *
	 * @param   array  the namespaces
	 * @param   bool   whether to prepend the namespace to the search path
	 * @return  void
	 */
	public static function add_namespaces(array $namespaces, $prepend = false)
	{
		if ( ! $prepend)
		{
			static::$namespaces = array_merge(static::$namespaces, $namespaces);
		}
		else
		{
			static::$namespaces = $namespaces + static::$namespaces;
		}
	}

	/**
	 * Returns the namespace's path or false when it doesn't exist.
	 *
	 * @param   string      the namespace to get the path for
	 * @return  array|bool  the namespace path or false
	 */
	public static function namespace_path($namespace)
	{
		if ( ! array_key_exists($namespace, static::$namespaces))
		{
			return false;
		}

		return static::$namespaces[$namespace];
	}

	/**
	 * Adds a classes load path.  Any class added here will not be searched for
	 * but explicitly loaded from the path.
	 *
	 * @param   string  the class name
	 * @param   string  the path to the class file
	 * @return  void
	 */
	public static function add_class($class, $path)
	{
		static::$classes[$class] = $path;
	}

	/**
	 * Adds multiple class paths to the load path. See {@see Autoloader::add_class}.
	 *
	 * @param   array  the class names and paths
	 * @return  void
	 */
	public static function add_classes($classes)
	{
		foreach ($classes as $class => $path)
		{
			static::$classes[$class] = $path;
		}
	}

	/**
	 * Aliases the given class into the given Namespace.  By default it will
	 * add it to the global namespace.
	 *
	 * <code>
	 * Autoloader::alias_to_namespace('Foo\\Bar');
	 * Autoloader::alias_to_namespace('Foo\\Bar', '\\Baz');
	 * </code>
	 *
	 * @param	string	$class		the class name
	 * @param	string	$namespace	the namespace to alias to
	 */
	public static function alias_to_namespace($class, $namespace = '')
	{
		$parts = explode('\\', $class);
		$root_class = $namespace.array_pop($parts);
		class_alias($class, $root_class);
	}

	/**
	 * Register's the autoloader to the SPL autoload stack.
	 *
	 * @return	void
	 */
	public static function register()
	{
		spl_autoload_register('Autoloader::load', true, true);
	}

	/**
	 * Returns the class with namespace prefix when available
	 *
	 * @param	string
	 * @return	bool|string
	 */
	protected static function is_core_class($class)
	{
		foreach (static::$core_namespaces as $ns)
		{
			if (array_key_exists($ns_class = $ns.'\\'.$class, static::$classes))
			{
				return $ns_class;
			}
		}

		return false;
	}

	/**
	 * Add a namespace for which classes may be used without the namespace prefix and
	 * will be auto-aliased to the global namespace.
	 * Prefixing the classes will overwrite core classes and previously added namespaces.
	 *
	 * @param	string
	 * @param	bool
	 * @return	void
	 */
	public static function add_core_namespace($namespace, $prefix = true)
	{
		if ($prefix)
		{
			array_unshift(static::$core_namespaces, $namespace);
		}
		else
		{
			array_push(static::$core_namespaces, $namespace);
		}
	}

	public static function load($class)
	{
		$loaded = false;
		$class = ltrim($class, '\\');
		$namespaced = ($pos = strripos($class, '\\')) !== false;

		if (empty(static::$auto_initialize))
		{
			static::$auto_initialize = $class;
		}
		if (array_key_exists($class, static::$classes))
		{
			include str_replace('/', DS, static::$classes[$class]);
			static::_init_class($class);
			$loaded = true;
		}
		elseif ( ! $namespaced and $class_name = static::is_core_class($class))
		{
			! class_exists($class_name, false) and include str_replace('/', DS, static::$classes[$class_name]);
			static::alias_to_namespace($class_name);
			static::_init_class($class);
			$loaded = true;
		}
		elseif ( ! $namespaced)
		{
			$file_path = str_replace('_', DS, $class);
			$file_path = APPPATH.'classes/'.strtolower($file_path).'.php';

			if (file_exists($file_path))
			{
				require $file_path;
				if ( ! class_exists($class, false) && class_exists($class_name = 'Fuel\\Core\\'.$class, false))
				{
					static::alias_to_namespace($class_name);
				}
				static::_init_class($class);
				$loaded = true;
			}
		}

		// This handles a namespaces class that a path does not exist for
		else
		{
			// need to stick the trimed \ back on...
			$namespace = '\\'.ucfirst(strtolower(substr($class, 0, $pos)));

			foreach (static::$namespaces as $ns => $path)
			{
				if (strncmp($ns, $namespace, $ns_len = strlen($ns)) === 0)
				{
					$class_no_ns = substr($class, $pos + 1);

					$file_path = strtolower($path.substr($namespace, strlen($ns) + 1).DS.str_replace('_', DS, $class_no_ns).'.php');
					if (is_file($file_path))
					{
						// Fuel::$path_cache[$class] = $file_path;
						// Fuel::$paths_changed = true;
						require $file_path;
						static::_init_class($class);
						$loaded = true;
						break;
					}
				}
			}
		}

		// Prevent failed load from keeping other classes from initializing
		if (static::$auto_initialize == $class)
		{
			static::$auto_initialize = null;
		}

		return $loaded;
	}

	/**
	 * Checks to see if the given class has a static _init() method.  If so then
	 * it calls it.
	 *
	 * @param	string	the class name
	 */
	private static function _init_class($class)
	{
		if (static::$auto_initialize === $class)
		{
			static::$auto_initialize = null;
			if (method_exists($class, '_init') and is_callable($class.'::_init'))
			{
				call_user_func($class.'::_init');
			}
		}
	}
}

/* End of file autoloader.php */