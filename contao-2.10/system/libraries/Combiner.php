<?php if (!defined('TL_ROOT')) die('You cannot access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2011 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Leo Feyer 2005-2011
 * @author     Leo Feyer <http://www.contao.org>
 * @package    System
 * @license    LGPL
 * @filesource
 */


/**
 * Class Combiner
 *
 * This class provides methods to combine CSS and JavaScript files.
 * @copyright  Leo Feyer 2011
 * @author     Leo Feyer <http://www.contao.org>
 * @package    Library
 */
class Combiner extends System
{

	/**
	 * Constants
	 */
	const CSS = '.css';
	const JS = '.js';

	/**
	 * Unique key
	 * @var string
	 */
	protected $strKey = '';

	/**
	 * Operation mode
	 * @var string
	 */
	protected $strMode = null;

	/**
	 * Files
	 * @var array
	 */
	protected $arrFiles = array();


	/**
	 * Add a file
	 * @param string
	 * @param string
	 * @param string
	 */
	public function add($strFile, $strVersion=false, $strMedia='screen')
	{
		// Determine the file type
		if (preg_match('/\.css$/', $strFile))
		{
			$strType = self::CSS;
		}
		elseif (preg_match('/\.js$/', $strFile))
		{
			$strType = self::JS;
		}
		else
		{
			throw new Exception("Invalid file $strFile");
		}

		// Set the operation mode
		if (!$this->strMode)
		{
			$this->strMode = $strType;
		}
		elseif ($this->strMode != $strType)
		{
			throw new Exception('You cannot mix different file types. Create another Combiner object instead.');
		}

		// Prevent duplicates
		if (isset($this->arrFiles[$strFile]))
		{
			return;
		}

		// Check the source file
		if (!file_exists(TL_ROOT . '/' . $strFile))
		{
			throw new Exception("File $strFile does not exist");
		}

		// Default version
		if ($strVersion === false)
		{
			$strVersion = VERSION .'.'. BUILD;
		}

		// Store the file
		$arrFile = array
		(
			'name' => $strFile,
			'version' => $strVersion,
			'media' => $strMedia
		);

		$this->arrFiles[$strFile] = $arrFile;
		$this->strKey .= '-f' . $strFile . '-v' . $strVersion . '-m' . $strMedia;
	}


	/**
	 * Return true if there are files
	 * @return boolean
	 */
	public function hasEntries()
	{
		return !empty($this->arrFiles);
	}


	/**
	 * Generate the combined file and return the path
	 * @return string
	 */
	public function getCombinedFile()
	{
		$strKey = substr(md5($this->strKey), 0, 12);

		// Load the existing file
		if (file_exists(TL_ROOT . '/system/scripts/' . $strKey . $this->strMode))
		{
			return TL_SCRIPT_URL . 'system/scripts/' . $strKey . $this->strMode;
		}

		// Create the file
		$objFile = new File('system/scripts/' . $strKey . $this->strMode);
		$objFile->truncate();

		foreach ($this->arrFiles as $arrFile)
		{
			$content = file_get_contents(TL_ROOT . '/' . $arrFile['name']);

			// TODO: add a hook

			// Handle style sheets
			if ($this->strMode == self::CSS)
			{
				// Adjust the file paths
				if (TL_MODE == 'BE')
				{
					$strDirname = dirname($arrFile['name']);

					// Remove relative paths
					while (strpos($content, 'url("../') !== false)
					{
						$strDirname = dirname($strDirname);
						$content = str_replace('url("../', 'url("', $content);
					}

					$strGlue = ($strDirname != '.') ? $strDirname . '/' : '';
					$content = str_replace('url("', 'url("../../' . $strGlue, $content);
				}

				$content = '@media ' . (($arrFile['media'] != '') ? $arrFile['media'] : 'all') . "{\n" . $content . "\n}";
			}

			$objFile->append($content);
		}

		unset($content);
		$objFile->close();

		// Create a gzipped version
		// TODO: make configurable in the back end settings
		if (function_exists('gzencode'))
		{
			$objFile = new File('system/scripts/' . $strKey . $this->strMode . '.gz');
			$objFile->write(gzencode(file_get_contents(TL_ROOT . '/system/scripts/' . $strKey . $this->strMode), 9));
			$objFile->close();
		}

		return TL_SCRIPT_URL . 'system/scripts/' . $strKey . $this->strMode;
	}
}

?>