<?php

namespace Helpers;

class FS{
	/**
     * @var cached reference to singleton instance
     */
    protected static $instance;

	private $_fileInfo = array();

	/**
     * gets the instance via lazy initialization (created on first usage)
     *
     * @return self
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * is not allowed to call from outside: private!
     *
     */
    private function __construct()
    {
    }
    /**
     * prevent the instance from being cloned
     *
     * @return void
     */
    private function __clone()
    {
    }
    /**
     * prevent from being unserialized
     *
     * @return void
     */
    private function __wakeup()
    {
    }

	/**
     * Чтобы не дергать постоянно файл который обрабатываем
     *
     * @access private
     * @param string $name ключ
     * @return string информация из pathinfo о обрабатываемом файле input
     */
    private function _pathinfo($file, $mode){
    	if(!is_scalar($file) && !is_scalar($mode)){
    		$file = $mode = '';
		}
		$flag = !(empty($file) || empty($mode));
		$f = MODX_BASE_PATH . $this->relativePath($file);
        if($flag && !isset($this->_fileInfo[$f], $this->_fileInfo[$f][$mode])){
            $this->_fileInfo[$f] = pathinfo($f);
        }
        $out = $flag && isset($this->_fileInfo[$f][$mode]) ? $this->_fileInfo[$f][$mode] : '';
        return $out;
    }

    public function takeFileDir($file){
    	return $this->_pathinfo($file, 'dirname');
    }

    public function takeFileBasename($file){ //file name with extension
    	return $this->_pathinfo($file, 'basename');
    }

    public function takeFileName($file){
    	return $this->_pathinfo($file, 'filename');
    }

	public function takeFileExt($file){
		return strtolower($this->_pathinfo($file, 'extension'));
	}

	public function checkFile($file){
		$f = is_scalar($file) ? MODX_BASE_PATH . $this->relativePath($file) : '';
		return (!empty($f) && is_file($f) && is_readable($f));
	}

    public function checkDir($path){
        $f = is_scalar($path) ? $this->relativePath($path) : '';
        return (!empty($f) && is_dir(MODX_BASE_PATH . $f) && is_readable(MODX_BASE_PATH . $f));
    }

	/**
	 * Если класс finfo и функция mime_content_type не доступны, то происходит сверка типов:
	 * 		- image/jpeg
	 *		- image/png
	 *		- image/gif
	 * Для всех остальных файлов будет присвоен тип application/octet-stream
	 *
     * @param $fname Имя файла
     * @return string MIME тип файла
     */
	public function takeFileMIME($file){
		$out = null;
        $path = $this->relativePath($file);
		if($this->checkFile($path)){
			$fname = MODX_BASE_PATH.$path;
			switch(true){
				/** need fileinfo extension */
				case (extension_loaded('fileinfo') && class_exists('\finfo')):{
					$fi = new \finfo(FILEINFO_MIME_TYPE);
					if ($fi) {
						$out = $fi->file($fname);
					}
					break;
				}
				case function_exists('mime_content_type'):{
					list($out) = explode(';', @mime_content_type($fname));
					break;
				}
				default:{
					/**
					 * @see: http://www.php.net/manual/ru/function.finfo-open.php#112617
					 */
					$fh=fopen($fname,'rb');
					if ($fh) {
						$bytes6=fread($fh,6);
						fclose($fh);
						switch(true){
							case ($bytes6===false): break;
							case (substr($bytes6,0,3)=="\xff\xd8\xff"): $out = 'image/jpeg'; break;
							case ($bytes6=="\x89PNG\x0d\x0a"): $out = 'image/png'; break;
							case ($bytes6=="GIF87a" || $bytes6=="GIF89a"): $out = 'image/gif'; break;
							default: $out = 'application/octet-stream'; break;
						}
					}
				}
			}
		}
		return $out;
	}
	
    public function makeDir($path, $perm = 0755){
        $flag = false;
        if (!$this->checkDir($path)){
            $path = MODX_BASE_PATH . $this->relativePath($path);
            $flag = mkdir($path, $this->toOct($perm), true);
        }
        return $flag;
    }

    /**
     * Копирование файла с проверкой на существование оригинального файла и созданием папок
     *
     * @param $from источник
     * @param $to получатель
     * @return bool статус копирования
     */
    public function copyFile($from, $to, $chmod = 0644){
        $flag = false;
        $from = MODX_BASE_PATH . $this->relativePath($from);
        $to = MODX_BASE_PATH . $this->relativePath($to);
        $dir = $this->takeFileDir($to);
        if($this->checkFile($from) && $this->makeDir($dir) && copy($from, $to)){
            chmod($to, $this->toOct($chmod));
            $flag = true;
        }
        return $flag;
    }

    /**
     * Получение относительного пути к файлу или папки
     *
     * @param string $path путь из которого нужно получить относительный
     * @param string $owner начальный путь который стоит вырезать
     * @return string относительный путь
     */
    public function relativePath($path, $owner = null){
        if(is_null($owner)){
            $owner = MODX_BASE_PATH;
        }
        if(!(empty($path) || !is_scalar($path)) && !preg_match("/^http(s)?:\/\/\w+/",$path)){
            $path = trim(preg_replace("#^".$owner."#", '', $path), '/');
        }else{
            $path = '';
        }
        return $path;
    }

    /**
     * Перевод строки/числа из восьмеричной/десятичной системы счисления в 8-ричную систему счисления
     * 0755 => 0755
     * '0755' => 0755
     * 755 => 0755
     * '755' => 0755
     *
     * @param  mixed $chmod строка или число в восьмеричной/десятичной системе счисления
     * @return int        число в восьмеричной системе счисления
     */
    public function toOct($chmod){
        $tmp = (string)$chmod;
        if(intval($tmp, 8) === octdec($chmod)){
            $tmp = (string)octdec($tmp);
        }
        return $tmp;
    }

    public function rmDir($dirPath) {
        $path = MODX_BASE_PATH . $this->relativePath($dirPath);
        if ($this->checkDir($path)) {
            $dirIterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
            $dirRecursiveIterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::CHILD_FIRST);
            foreach($dirRecursiveIterator as $path) {
                $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
            }
            rmdir($dirPath);
        }
    }

    public function getInexistantFilename($file, $full = false) {
        $i = 1;
        $file = MODX_BASE_PATH.$this->relativePath($file);
        $out = $file;
        while ($this->checkFile($file)) {
            $i++;
            $out = $full ? $this->takeDir($file).'/' : '';
            $out .= $this->takeFileName($file)."({$i}).".$this->takeFileExt($file);
        }
        return $out;
    }
}