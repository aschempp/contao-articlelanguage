<?php

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 *
 * PHP version 5
 * @copyright  terminal42 gmbh 2009-2013
 * @author     Andreas Schempp <andreas.schempp@terminal42.ch>
 * @author     Kamil Kuźmiński <kamil.kuzminski@terminal42.ch>
 * @license    LGPL
 */

namespace Contao;


class ModuleArticleLanguage extends \ModuleChangeLanguage
{
	protected $strTemplate = 'mod_articlelanguage';


	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### ARTICLE LANGUAGE ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = 'typolight/main.php?do=modules&amp;act=edit&amp;id=' . $this->id;

			return $objTemplate->parse();
		}

		return parent::generate();
	}


	protected function compile()
	{
		if (isset($_GET['alng']))
		{
			$_SESSION['ARTICLE_LANGUAGE'] = $this->Input->get('alng');
			$this->redirect(preg_replace('@[?|&]alng='.$this->Input->get('alng').'@', '', $this->Environment->request));
		}

		global $objPage;
		$time = time();
		$arrLang = array();

		// Prepare custom language texts
		$this->customLanguageText = deserialize($this->customLanguageText);
		if (!is_array($this->customLanguageText)) $this->customLanguageText = array();
		$customLanguageText = array();
		foreach ($this->customLanguageText as $arrText)
		{
			$customLanguageText[strtolower($arrText['value'])] = $arrText;
		}

		$objArticles = $this->Database->prepare("SELECT p.*, a.language AS alng FROM tl_article a LEFT OUTER JOIN tl_page p ON a.pid=p.id WHERE (a.pid=? OR a.pid=?)" . (!BE_USER_LOGGED_IN ? " AND (a.start='' OR a.start<?) AND (a.stop='' OR a.stop>?) AND a.published=1" : "") . " GROUP BY a.language ORDER BY a.sorting")
									  ->execute($objPage->id, $objPage->languageMain, $time, $time);

		$c = 0;
		$count = $objArticles->numRows;

		while( $objArticles->next() )
		{
			if (strlen($_SESSION['ARTICLE_LANGUAGE']) && $objArticles->alng == $_SESSION['ARTICLE_LANGUAGE'])
			{
				$GLOBALS['TL_LANGUAGE'] = $_SESSION['ARTICLE_LANGUAGE'];
			}

			if ($this->hideActiveLanguage && $objArticles->alng == $_SESSION['ARTICLE_LANGUAGE'])
				continue;

			// Build template array
			if (strlen($objArticles->alng))
			{
				$arrLang[$c] = array
				(
					'active'	=> ($_SESSION['ARTICLE_LANGUAGE'] == $objArticles->alng ? true : false),
					'label'		=> ($this->customLanguage ? (isset($customLanguageText[$objArticles->alng]) ? $customLanguageText[$objArticles->alng]['label'] : strtoupper($objArticles->alng)) : strtoupper($objArticles->alng)),
					'href'		=> $this->Environment->request.(strpos($this->Environment->request, '?') === false ? '?' : '&').'alng='.$objArticles->alng,
					'language'	=> $arrRootPage['language'],
					'class'		=> 'lang-' . $objArticles->alng . ($c == 0 ? ' first' : '') . ($c == $count-1 ? ' last' : ''),
					'icon'		=> 'system/modules/changelanguage/media/images/'.$objArticles->alng.'.gif',
				);
			}
			else
			{
				$objRootPage = $this->Database->prepare("SELECT * FROM tl_page WHERE id=?")->limit(1)->execute($objPage->rootId);

				$arrLang[$c] = array
				(
					'active'	=> !strlen($_SESSION['ARTICLE_LANGUAGE'] ? true : false),
					'label'		=> ($this->customLanguage ? (isset($customLanguageText[$objRootPage->language]) ? $customLanguageText[$objRootPage->language]['label'] : strtoupper($objRootPage->language)) : strtoupper($objRootPage->language)),
					'href'		=> $this->Environment->request.(strpos($this->Environment->request, '?') === false ? '?' : '&').'alng=',
					'language'	=> $objRootPage->language,
					'class'		=> 'lang-' . $objRootPage->language . ($c == 0 ? ' first' : '') . ($c == $count-1 ? ' last' : ''),
					'icon'		=> 'system/modules/changelanguage/media/images/'.$objRootPage->language.'.gif',
				);
			}

			$c++;
		}

		if ($this->customLanguage && count($this->customLanguageText))
        {
	        usort($arrLang, array($this, 'orderByCustom'));
       	}

		$this->Template->useImages = $this->useImages;
        $this->Template->languages = (!is_array($arrLang) || empty($arrLang)) ? array() : $arrLang;
	}
}

