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
 * @license    LGPL
 */


/**
 * List
 */
$GLOBALS['TL_DCA']['tl_article']['list']['label']['fields'][] = 'language';
$GLOBALS['TL_DCA']['tl_article']['list']['label']['format'] .= ' <span style="color:#b3b3b3;">(%s)</span>';


/**
 * Palettes
 */
$GLOBALS['TL_DCA']['tl_article']['palettes']['default'] = str_replace(',title,', ',title,language,', $GLOBALS['TL_DCA']['tl_article']['palettes']['default']);


/**
 * Fields
 */
$GLOBALS['TL_DCA']['tl_article']['fields']['title']['eval']['tl_class'] = 'w50';

$GLOBALS['TL_DCA']['tl_article']['fields']['language'] = array
(
	'label'						=> &$GLOBALS['TL_LANG']['tl_article']['language'],
	'exclude'					=> true,
	'inputType'					=> 'text',
	'reference'					=> $this->getLanguages(),
	'eval'						=> array('rgxp'=>'alpha', 'maxlength'=>2, 'nospace'=>true, 'tl_class'=>'w50')
);

