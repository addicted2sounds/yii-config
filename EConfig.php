<?php
/**
 * Config class file.
 * @author Sers <sersONEd@gmail.com>
 * @copyright Copyright &copy; www.sersid.ru 2013-
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 */
class EConfig extends CApplicationComponent
{
	/**
	 * The ID of the connection component
	 * @var string
	 */
	public $idConnection = 'db';

	/**
	 * The ID of the cache component
	 * @var string
	 */
	public $idCache;

	/**
	 * The key identifying the value to be cached
	 * @var string
	 */
	public $cacheKey = 'econfig.component';

	/**
	 * Config table name
	 * @var string
	 */
	public $tableName = 'configs';
	
	/**
	 * Method for coding and decoding
	 * serialize or json
	 * @var string
	 */
	public $coding = 'serialize';

	/**
	 * @var CDbConnection
	 */
	private $_db;

	/**
	 * Use cache
	 * @var boolean
	 */
	private $_useCache = false;

	/**
	 * @var CCache
	 */
	private $_cache;

	/**
	 * Configs
	 * @var array
	 */
	private $_configs;

	/**
	 * Init EConfig component
	 * @throws CException
	 */
	public function init()
	{
		// Get db component
		$this->_db = Yii::app()->getComponent($this->idConnection);

		if(!$this->_db instanceof CDbConnection)
			throw new CException("EConfig.idConnection \"{$this->idConnection}\" is invalid.");

		// Get cache component
		if($this->idCache !== NULL)
		{
			$this->_cache = Yii::app()->getComponent($this->idCache);
			
			if(!$this->_cache instanceof CCache && !$this->_cache instanceof CDummyCache)
				throw new CException("EConfig.idCache \"{$this->idCache}\" is invalid.");

			$this->_useCache = true;
		}

		// Load configs
		$this->_loadData();
	}

	/**
	 * Get config var
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 */
	public function get($name, $default = null)
	{
		if(is_array($name))
		{
			$return = array();
			foreach($name as $key => $value)
			{
				if(is_int($key))
					$return[$value] = $this->_get($value, $default);
				else
					$return[$key] = $this->_get($key, $value);
			}
			return $return;
		}
		else
		{
			return $this->_get($name, $default);
		}
	}

	/**
	 * Find and decode config var
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 */
	public function _get($name, $default = null)
	{
		return array_key_exists($name, $this->_configs) ? $this->_decode($this->_configs[$name]) : $default;
	}

	/**
	 * Set config vars
	 * @param string/array $name
	 * @param mixed $value
	 */
	public function set($name, $value = null)
	{
		if(is_array($name))
		{
			$arInsert = array();
			$arDelete = array();
			foreach($name as $key => $val)
			{
				$val = $this->_encode($val);

				$arInsert[] = array(
					'key' => $key,
					'value' => $val,
				);

				$arDelete[] = $key;

				$this->_configs[$key] = $val;
			}
			if(count($arInsert) > 0)
			{
				$this->_db->createCommand()
						->delete($this->tableName, array('IN', 'key', $arDelete));

				$this->_db->schema->commandBuilder
						->createMultipleInsertCommand($this->tableName, $arInsert)
						->execute();
			}
		}
		else
		{
			$value = $this->_encode($value);

			if(array_key_exists($name, $this->_configs) === false)
			{
				$this->_db->createCommand()->insert($this->tableName, array(
					'key' => $name,
					'value' => $value,
				));
			}
			else
			{
				$this->_db->createCommand()->update($this->tableName, array(
					'value' => $value,
				), '`key`=:key', array(':key' => $name));
			}

			$this->_configs[$name] = $value;
		}

		if($this->_useCache)
			$this->_cache->set($this->cacheKey, $this->_configs);
	}

	/**
	 * Loading config data
	 */
	private function _loadData()
	{
		$configs = array();
		if($this->_useCache)
		{
			$configs = $this->_cache->get($this->cacheKey);
			
			if(!is_array($configs))
				$configs = array();
		}

		if(count($configs) === 0)
		{
			$dbReader = $this->_db->createCommand("SELECT * FROM {$this->tableName}")->query();

			while(false !== ($row = $dbReader->read()))
			{
				$configs[$row['key']] = $row['value'];
			}

			if($this->_useCache)
				$this->_cache->set($this->cacheKey, $configs);
		}

		$this->_configs = $configs;
	}
	
	/**
	 * Encoding variable with the specified coding method
	 * @param mixed $value
	 * @return mixed
	 * @throws CException
	 */
	private function _encode($value)
	{
		switch($this->coding)
		{
			case 'serialize':
				return serialize($value);
				break;

			case 'json':
				return CJSON::encode($value);
				break;
			
			default:
				throw new CException("EConfig.coding \"{$this->coding}\" is invalid.");
				break;
		}
	}
	
	/**
	 * Decoding variable with the specified coding method
	 * @param mixed $value
	 * @return mixed
	 * @throws CException
	 */
	private function _decode($value)
	{
		switch($this->coding)
		{
			case 'serialize':
				return unserialize($value);
				break;

			case 'json':
				return CJSON::decode($value);
				break;
			
			default:
				throw new CException("EConfig.coding \"{$this->coding}\" is invalid.");
				break;
		}
	}
}