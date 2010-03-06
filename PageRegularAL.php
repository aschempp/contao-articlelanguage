<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * TYPOlight webCMS
 * Copyright (C) 2005-2009 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at http://www.gnu.org/licenses/.
 *
 * PHP version 5
 * @copyright  Andreas Schempp 2009
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 * @version    $Id$
 */


class PageRegularAL extends PageRegular
{
	
	/**
	 * Generate a front end module and return it as HTML string. Only take articles of the current language.
	 * @param integer
	 * @param string
	 * @return string
	 */
	protected function getFrontendModule($intId, $strColumn='main')
	{
		global $objPage;
		$this->import('Database');

		if (!strlen($intId))
		{
			return '';
		}

		// Articles
		if ($intId == 0)
		{
			// Show a particular article only
			if ($this->Input->get('articles') && $objPage->type == 'regular')
			{
				list($strSection, $strArticle) = explode(':', $this->Input->get('articles'));

				if (is_null($strArticle))
				{
					$strArticle = $strSection;
					$strSection = 'main';
				}

				if ($strSection == $strColumn)
				{
					return $this->getArticle($strArticle);
				}
			}

			// HOOK: trigger article_raster_designer extension
			elseif (in_array('article_raster_designer', $this->Config->getActiveModules()))
			{
				return RasterDesigner::load($objPage->id, $strColumn);
			}

			$time = time();

			// Show all articles of the current column
			$objArticles = $this->Database->prepare("SELECT id FROM tl_article WHERE (pid=? OR pid=?) AND language=? AND inColumn=?" . (!BE_USER_LOGGED_IN ? " AND (start='' OR start<?) AND (stop='' OR stop>?) AND published=1" : "") . " ORDER BY sorting")
										  ->execute($objPage->id, (strlen($_SESSION['ARTICLE_LANGUAGE']) ? $objPage->languageMain : 0), (strlen($_SESSION['ARTICLE_LANGUAGE']) ? $_SESSION['ARTICLE_LANGUAGE'] : ''), $strColumn, $time, $time);
										  
			if (($count = $objArticles->numRows) < 1)
			{
				$objArticles = $this->Database->prepare("SELECT id FROM tl_article WHERE pid=? AND language='' AND inColumn=?" . (!BE_USER_LOGGED_IN ? " AND (start='' OR start<?) AND (stop='' OR stop>?) AND published=1" : "") . " ORDER BY sorting")
											  ->execute($objPage->id, $strColumn, $time, $time);

				if (($count = $objArticles->numRows) < 1)
				{
					return '';
				}
			}

			$return = '';

			while ($objArticles->next())
			{
				$return .= $this->getArticle($objArticles->id, (($count > 1) ? true : false), false, $strColumn);
			}

			return $return;
		}

		// Other modules
		$objModule = $this->Database->prepare("SELECT * FROM tl_module WHERE id=?")
									->limit(1)
									->execute($intId);

		if ($objModule->numRows < 1)
		{
			return '';
		}

		// Show to guests only
		if ($objModule->guests && FE_USER_LOGGED_IN && !BE_USER_LOGGED_IN && !$objModule->protected)
		{
			return '';
		}

		// Protected element
		if (!BE_USER_LOGGED_IN && $objModule->protected)
		{
			if (!FE_USER_LOGGED_IN)
			{
				return '';
			}

			$this->import('FrontendUser', 'User');
			$arrGroups = deserialize($objModule->groups);
	
			if (is_array($arrGroups) && count(array_intersect($this->User->groups, $arrGroups)) < 1)
			{
				return '';
			}
		}

		$strClass = $this->findFrontendModule($objModule->type);

		if (!$this->classFileExists($strClass))
		{
			$this->log('Module class "'.$GLOBALS['FE_MOD'][$objModule->type].'" (module "'.$objModule->type.'") does not exist', 'Controller getFrontendModule()', TL_ERROR);
			return '';
		}

		$objModule->typePrefix = 'mod_';
		$objModule = new $strClass($objModule, $strColumn);


		$strBuffer = $objModule->generate();

		// Disable indexing if protected
		if ($objModule->protected && !preg_match('/^\s*<!-- indexer::stop/i', $strBuffer))
		{
			$strBuffer = "\n<!-- indexer::stop -->$strBuffer<!-- indexer::continue -->\n";
		}

		return $strBuffer;
	}
	
	
	/**
	 * Generate an article and return it as string
	 * @param integer
	 * @param boolean
	 * @param boolean
	 * @param string
	 * @return string
	 */
	protected function getArticle($varId, $blnMultiMode=false, $blnIsInsertTag=false, $strColumn='main')
	{
		if (!strlen($varId))
		{
			return '';
		}

		global $objPage;
		$this->import('Database');

		// Get article
		$objArticle = $this->Database->prepare("SELECT *, author AS authorId, (SELECT name FROM tl_user WHERE id=author) AS author FROM tl_article WHERE (id=? OR alias=?)" . (!$blnIsInsertTag ? " AND (pid=? OR pid=?)" : ""))
									 ->limit(1)
									 ->execute((is_numeric($varId) ? $varId : 0), $varId, $objPage->id, $objPage->languageMain);

		if ($objArticle->numRows < 1)
		{
			// Do not index the page
			$objPage->noSearch = 1;
			$objPage->cache = 0;

			// Send 404 header
			header('HTTP/1.1 404 Not Found');
			return '<p class="error">' . sprintf($GLOBALS['TL_LANG']['MSC']['invalidPage'], $varId) . '</p>';
		}

		if (!file_exists(TL_ROOT . '/system/modules/frontend/ModuleArticle.php'))
		{
			$this->log('Class ModuleArticle does not exist', 'Controller getArticle()', TL_ERROR);
			return '';
		}

		// Print article as PDF
		if ($this->Input->get('pdf') == $objArticle->id)
		{
			$this->printArticleAsPdf($objArticle);
		}

		$objArticle->headline = $objArticle->title;
		$objArticle->multiMode = $blnMultiMode;

		$objArticle = new ModuleArticle($objArticle, $strColumn);
		return $objArticle->generate($blnIsInsertTag);
	}
}

