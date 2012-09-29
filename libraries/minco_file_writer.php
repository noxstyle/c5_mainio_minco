<?php
/**
 * This class is partly based on the original Minify logic in the
 * Minify libraries. With permission from the authors, this code
 * is also licenced under the MIT license.
 * 
 */
class MincoFileWriter {
	
	private static $_headers = array(
		'Content-Type' => 'text/plain'
	);
	
	private static function _sendHeaders() {
		foreach (self::$_headers as $name => $val) {
		    header($name . ': ' . $val);
		}
	}
	
	private static function _setHeader($name, $val) {
		self::$_headers[$name] = $val;
	}
	
	public static function writeStatic($key, $v = false, $fileExtension = '', $saveDir = MINCO_RESOURCES_SAVE_DIR) {
		$r = self::_parseAndCombine($key);
		
		if (!file_exists($saveDir)) {
			if (!@mkdir($saveDir)) {
				throw new Exception(t("The directory for saving the minco assets does not exist: %s", $saveDir));
			}
		}
		
		$filename = $key;
		if (strlen($v) > 0) {
			$filename .= '.' . $v;
		}
		if (strlen($fileExtension) > 0) {
			$filename .= '.' . $fileExtension;
		}
		$file = $saveDir . '/' . $filename;
		if (MINCO_BYPASS_CACHE || !file_exists($file)) {
			if ($fh = @fopen($file, 'w')) {
				fwrite($fh, $r);
				fclose($fh);
			} else {
				throw new Exception(t("Cannot write to the minco assets saving directory: %s", $dir));
			}
		}
		return $filename;
	}
	
	public static function respondMinifyRequest() {
		if (!file_exists(MINCO_LISTS_FILE)) {
			echo "/* File lists not defined! */";
			exit;
		}
		ini_set('zlib.output_compression', '0');
		
		$r = ""; // Result string
		if (isset($_GET['k'])) {
			$key = $_GET['k'];
			$v = isset($_GET['v']) ? $_GET['v'] : '';
			
			// Browser cache
			if (!self::_checkClientCache()) {
				// Server cache
				$ca = Cache::get('minified_resource', $key);
				if (MINCO_BYPASS_CACHE || $ca == false) {
					$r = self::_parseAndCombine($key);
					
					Cache::set('minified_resource', $key, array(
						'content' => $r,
						'headers' => self::$_headers
					));
				} else {
					$r = $ca['content'];
					self::$_headers = $ca['headers'];
				}
				
			}
		}
		self::_sendHeaders();
		echo $r;
	}
	
	private static function _checkClientCache() {
	    require_once 'HTTP/ConditionalGet.php';
	    $cgOptions = array(
	        'lastModifiedTime' => 0,
	        'isPublic' => true,
	        'encoding' => '',
	        'maxAge' => 1800
	    );
		$cg = new HTTP_ConditionalGet($cgOptions);
		if (MINCO_CLIENT_CACHE && $cg->cacheIsValid) {
			// client's cache is valid
			$cg->sendHeaders();
			return true;
		} else {
			// client will need output
			self::$_headers = $cg->getHeaders();
			unset($cg);
		}
		return false;
	}
	
	private static function _parseAndCombine($key) {
		$r = "";
		require MINCO_LISTS_FILE;
		if (is_array($minifyFilesList) && array_key_exists($key, $minifyFilesList)) {
			$DIR_ROOT = $_SERVER['DOCUMENT_ROOT'];
			$noMinPattern = '/[-\\.]min\\.(?:js|css)$/i';
			$files = $minifyFilesList[$key];
			if (is_array($files)) {
				$first = $files[0];
				$ext = strtolower(substr($first, strrpos($first, ".")+1));
				$cls = null;
				$func = "minify";
				$opts = null;
				if ($ext === 'css') {
					Loader::library("3rdparty/minify-2.1.5/min/lib/Minify/CSS", "mainio_minco");
					$cls = "Minify_CSS";
					self::_setHeader('Content-Type', "text/css");
				} else if ($ext === 'js') {
					Loader::library("3rdparty/minify-2.1.5/min/lib/JSMin", "mainio_minco");
					$cls = "JSMin";
					self::_setHeader('Content-Type', "application/x-javascript");
				}
				$canMinify = class_exists($cls) && method_exists($cls, $func);
				
				foreach ($files as $f) {
					$path = $DIR_ROOT . $f;
					if (file_exists($path)) {
						if (($cont = @file_get_contents($path)) !== false) {

							// Replace image paths
							if(MINICO_REPLACE_CSS_IMG_PATHS) {
								if($ext === 'css') {
									$cont = self::_replaceCSSImagePaths($f, $cont);
								}
							}
							
							if ($canMinify && !preg_match($noMinPattern, basename($f))) {
								// Minify only files that don't have names matching the $noMinPattern
								// This is also why we minify the files one at a time
								$args = array($cont);
								if (is_array($opts)) {
									array_push($args, $opts);
								}
								if ($ext === 'css') {
									$opts = array(
										'currentDir' => dirname($path)
									);
								}
								$cont = call_user_func_array(array($cls, $func), $args);
							}
							$r .= $cont . PHP_EOL;
						}
					}
				}
				$len = ((function_exists('mb_strlen') && ((int)ini_get('mbstring.func_overload') & 2))
                		? mb_strlen($r, '8bit')
                		: strlen($r));
				self::_setHeader('Content-Length', $len);
			}
		}
		return $r;
	}

	/**
	 * Replaces all css image paths with their actual relation to the installation root
	 * @param String $filePath Path to .css file
	 * @param String $cont .css file contents
	 * @return String
	 */
	private function _replaceCSSImagePaths($filePath, $cont) {
		preg_match_all("@url\((.*)\)@", $cont, $matches);
		if(!empty($matches[1]))
		{
			foreach($matches[1] as $img)
			{
				// Get rid of quotation marks and whitespaces, if any
				$imgPath = str_replace(array('\'', '"'), '', trim($img));
				
				// Check that the image is not loaded from online
				if(strpos($img, 'http://') === false)
				{
					// Get the amount of levels to descent on folder structure
					$levelsDown = substr_count($img, '../');
					
					if($levelsDown > 0)
					{
						$walkDown = explode('/', $filePath);
						$cssFile = $walkDown[count($walkDown) - 1];

						// Get rid of the last element in .css path, i.e. stylesheet name
						// and descend to the correct level in folder hierarchy
						array_pop($walkDown);					
						for($i=0;$i<$levelsDown;$i++) {
							array_pop($walkDown);
						}
						 
						$walkDown = implode('/', $walkDown);
						$newImgPath = $walkDown.'/'.str_replace('../', '', $imgPath);
					}
					else
					{
						$cssPath = explode('/',$filePath);
						array_pop($cssPath); // Removes stylesheet
						$newImgPath = implode('/',$cssPath) .'/'. $imgPath;
					}
					
					// Replace the image path in file
					$cont = str_replace($img, $newImgPath, $cont);
									
				}
			}
		}
		return $cont;
	}
	
}
