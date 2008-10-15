<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

// +----------------------------------------------------------------------+
// | Akelos Framework - http://www.akelos.org                             |
// +----------------------------------------------------------------------+
// | Copyright (c) 2002-2007, Akelos Media, S.L.  & Bermi Ferrer Martinez |
// | Released under the GNU Lesser General Public License, see LICENSE.txt|
// +----------------------------------------------------------------------+

/**
* @package ActiveRecord
* @subpackage Behaviours
* @author Arno Schneider <arno a.t. bermilabs dot com>
* @copyright Copyright (c) 2002-2007, Akelos Media, S.L. http://www.akelos.org
* @license GNU Lesser General Public License <http://www.gnu.org/copyleft/lesser.html>
*/

require_once(AK_LIB_DIR.DS.'AkActiveRecord'.DS.'AkObserver.php');


class ActsAsSluggable extends AkObserver
{
    var $_instance;
    var $_slug_source;
    var $_slug_target;
    var $_validated = false;
    var $custom_replacements = array();

    function ActsAsSluggable(&$ActiveRecordInstance, $options = array())
    {
        $this->_instance = &$ActiveRecordInstance;
        $this->init($options);
    }
    
    function init($options = array())
    {
        if (isset($options['slug_source'])) {
            $this->_slug_source = $options['slug_source'];
        }
        if (isset($options['slug_target'])) {
            $this->_slug_target = $options['slug_target'];
        }
        if (isset($options['replacements']) && is_array($options['replacements'])) {
            $this->custom_replacements = $options['replacements'];
        }
        if (isset($this->_slug_source) && isset($this->_slug_target)) {
            $this->observe(&$this->_instance);
        }
    }

    function afterValidation(&$record)
    {
        return $this->_createSlug(&$record);
    }
    
    
    function _generateSlug(&$record)
    {
        $sourceData = false;
        $slug = false;
        if (method_exists($record,$this->_slug_source)) {
            $sourceData = $record->{$this->_slug_source}();
        } else if (isset($record->{$this->_slug_source})) {
            $sourceData = $record->{$this->_slug_source};
        }
        if (!empty($sourceData) && $record->hasColumn($this->_slug_target)) {
            $slug = $this->_getUrlSafeName(&$record,$sourceData);
            $slug = $this->_getUniqueSlug(&$record, $slug);
        }
        return $slug;
    }
    function _createSlug(&$record)
    {
        $slug = $this->_generateSlug(&$record);
        if ($slug!==false) {
            $record->set($this->_slug_target,$slug);
        }
        return true;
    }
    
    function _getUniqueSlug(&$record, $slug)
    {
        $tries = 1;
        $orgSlug = $slug;
        while (($found = $record->findFirst($this->_slug_target.' = ? AND id <> ?',$slug,$record->id==null?-1:$record->id))) {
            $slug =$orgSlug.'-'.$tries;
            $tries++;
            if($tries>20) {
                $slug = $orgSlug.'-'.md5(time());
                break;
            }
        }
        return $slug;
        
    }
    
    function _getUrlSafeName(&$record,$name)
    {
        
        $safe_name = strtolower($name);
        $safe_name = str_replace(array('ñ','ä','ü','ö','ß'),array('ny','ae','ue','oe','ss'), $safe_name);
        $safe_name = AkInflector::unaccent($safe_name);
        $specialCharsUnsafe =array("¡","!"," ","`","]","[","~","^","\\","|","}","{","%","#",">","<",
                                       "'",'"',"",'@','$','&',
                                       ',','/',':',';','=','?');
        $specialCharsSafe = array("-exclamation-","-exclamation-","-","-accent-","-rbracket-","-lbracket-","-tilde-","-caret-","-backslash-","-pipe-","-rbrace-","-lbrace-","-percent-","-hash-","-more-","-less-",
                                  "","",'-','-at-','-dollar-','-and-',
                                  '-comma-','-or-','-colon-','-semicolon-','-equals-','-question-');
        
        
        foreach (array_keys($record->sluggable->custom_replacements) as $customKey) {
            array_unshift($specialCharsUnsafe,$customKey);
            array_unshift($specialCharsSafe,$record->sluggable->custom_replacements[$customKey]);
        }
        
        $safe_name = str_replace($specialCharsUnsafe,
                                 $specialCharsSafe,
                                 $safe_name);
        $safe_name = preg_replace('/-+/','-',$safe_name);
        $safe_name = trim($safe_name,'-');
        return $safe_name;
    }
}
?>