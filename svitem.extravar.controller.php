<?php
/**
 * @brief svitem 출력항목 클래스
 * @author singleview(root@singleview.co.kr)
 */

/**
 * @brief 출력항목
 * extra_var는 관리자가 추가한 extended var와 목록화면과 상세 화면의 기본 변수인 default_var로 구분
 */
class svitemExtraVarController extends svitem
{
	const A_PAGE_TYPE = ['catalog'=>'svitem', 'detail'=>'svitem.detail' ];
	const A_DEFAULT_VAR_CATALOG = ['checkbox', 'quantity', 'cart_buttons'];
	const A_DEFAULT_VAR_DETAIL = [];
/**
 * @brief 확장 변수 자체를 추가함
 **/
	public function addExtendedVar($oArgs)
	{
		// check column_name
		if($this->_checkColumnName($oArgs->module_srl, $oArgs->column_name))
			return new BaseObject(-1, 'msg_invalid_column_name');
		// Default values
		if(in_array($oArgs->column_type, array('checkbox','select','radio')) && count($oArgs->default_value) ) 
			$oArgs->default_value = serialize($oArgs->default_value);
		else 
			$oArgs->default_value = '';

		if(!$oArgs->extra_srl) // Add if not exists.
		{
			$oArgs->list_order = $oArgs->extra_srl = getNextSequence();
			$oRst = executeQuery('svitem.insertItemExtra', $oArgs);
			$this->setMessage('success_registed');
		}
		else // update if extra_srl exists.
		{
			$oRst = executeQuery('svitem.updateItemExtra', $oArgs);
			$this->setMessage('success_updated');
		}
		unset($oArgs);
		if(!$oRst->toBool()) 
			return $oRst;
		unset($oRst);
		return new BaseObject();
	}
/**
 * @brief 확장 변수 자체를 삭제함
 **/
	public function removeExtendedVar($oParam)
	{
		if(!$oParam->nModuleSrl)
			return new BaseObject(-1, 'msg_invalid_module_srl');
		if(!$oParam->nExtraSrl)
			return new BaseObject(-1, 'msg_invalid_extra_srl');
        $oTmpArg = new stdClass();
		$oTmpArg->extra_srl = $oParam->nExtraSrl;
		$oRmRst = executeQuery('svitem.deleteItemExtra', $oTmpArg);
		if(!$oRmRst->toBool())
			return $oRmRst;
		unset($oTmpArg);
		unset($oRmRst);
		return new BaseObject();
	}
/**
 * @brief 추가된 확장 변수에 값을 등록함
 **/
	public function registerOnExtendedVar($oInArgs)
	{
		if( !$oInArgs->nModuleSrl )
			return new BaseObject(-1, 'msg_invalid_module_srl');
		if( !$oInArgs->nItemSrl )
			return new BaseObject(-1, 'msg_invalid_item_srl');
		foreach( $oInArgs->oExtendedVar as $sVarTitle => $sVal )
		{
			$oExArgs->item_srl = $oInArgs->nItemSrl;
			$oExArgs->name = $sVarTitle;
			$oExArgs->value = $sVal;
			$oRst = executeQuery('svitem.deleteSvitemExtraVars', $oExArgs);
			if(!$oRst->toBool()) 
				return $oRst;
			unset($oRst);
			$oRst = executeQuery('svitem.insertSvitemExtraVars', $oExArgs);
			if(!$oRst->toBool()) 
				return $oRst;
			unset($oRst);
			unset($oExArgs);
		}
		return new BaseObject();
	}
/**
 * @brief extended var, default var를 병합한 변수 목록을 목록 페이지와 상세 페이지 구분하여 저장
 * svitem.admin.controller.php::_insertDisplayConfig()에서 호출
 * $oParam->nModuleSrl, $oParam->sList
 * @return 
 **/
	public function saveExtraVars($oParam)
	{
		$aChoosedExtraVars = explode(',', $oParam->sList);
		if(!count($aChoosedExtraVars))
			return new BaseObject(-1, 'msg_invalid_request');
		$aChoosedList = [];
		foreach($aChoosedExtraVars as $val) 
		{
			$val = trim($val);
			if(!$val)
				continue;
			if(substr($val,0,10)=='extra_vars')
				$val = substr($val,10);
			$aChoosedList[] = $val;
		}
		$oModuleController = &getController('module');
		$oRst = $oModuleController->insertModulePartConfig(self::A_PAGE_TYPE[$oParam->sPageType], $oParam->nModuleSrl, $aChoosedList);
		if(!$oRst->toBool())
			return $oRst;
		return new BaseObject();
	}
/**
 * @brief extended var, default var를 병합한 표시 변수 목록 출력
 * svitem.admin.view.php::dispSvitemAdminListDisplaySetup()에서 호출
 * svitem.admin.view.php::dispSvitemAdminDetailDisplaySetup()에서 호출
 * @return 
 **/
	public function getExtraVarsConfiguration($oParam)
	{
		if(!$oParam->nModuleSrl)
			return new BaseObject(-1, 'msg_invalid_module_srl');
		if($oParam->sPageType != 'catalog' && $oParam->sPageType != 'detail')
			return new BaseObject(-1, 'msg_invalid_page_type');
		// extended var, default var를 병합한 변수 목록 가져오기
		// 기본 변수 가져오기
		switch($oParam->sPageType)
		{
			case 'catalog':
				$aVirtualVars = self::A_DEFAULT_VAR_CATALOG;
				break;
			case 'detail':
				$aVirtualVars = self::A_DEFAULT_VAR_DETAIL;
				break;
		}
		// 확장 변수 가져오기
		$oRst = $this->getExtendVarsByModuleSrl($oParam->nModuleSrl);
		if(!$oRst->toBool()) 
			return $oRst;
		$aFormList = $oRst->get('aFinalExtraVar');
		unset($oRst);

		// 기본변수 + 확장변수 = 전체변수
		$aExtraVars = [];
		foreach($aVirtualVars as $sVarTitle)
			$aExtraVars[$sVarTitle] = new svExtenVar($oParam->nModuleSrl, -1,  $sVarTitle, Context::getLang($sVarTitle),'default', '', null, null);
		if(count($aFormList))
		{
			$nIdx = 1;
			foreach($aFormList as $nExtraSrl => $oExtra)
			{
				$aExtraVars[$oExtra->column_name] = new svExtenVar($oExtra->nModuleSrl, $nIdx, $oExtra->column_name, $oExtra->column_title, $oExtra->column_type, $oExtra->default_value, $oExtra->description, $oExtra->required, 'N', null);
				$nIdx++;
			}
		}
		// 저장된 표시 변수 목록을 가져옴.
		$oModuleModel = &getModel('module');
		$aRegisteredVars = $oModuleModel->getModulePartConfig(self::A_PAGE_TYPE[$oParam->sPageType], $oParam->nModuleSrl);
		unset($oModuleModel);
		
		$aDisplayingVars = [];
		$aHiddenVars = [];
		foreach($aExtraVars as $sVarName => $oVar)
		{
			if(in_array($sVarName, (array)$aRegisteredVars)) // 저장 변수명이면 표시 변수 목록으로
				$aDisplayingVars[$sVarName] = $oVar->title;
			else // 저장 변수명이 아니면 숨김 변수 목록으로
				$aHiddenVars[$sVarName] = $oVar->title;
		}
		$oRst = new BaseObject();
		$oRst->add('aDisplayingVars', $aDisplayingVars);
		$oRst->add('aHiddenVars', $aHiddenVars);
		unset($aRegisteredVars);
		unset($aDisplayingVars);
		unset($aHiddenVars);
		return $oRst;
	}
/**
 * @brief module_Srl에 따라 확장 변수 목록을 가져옴
 * svitem.admin.view.php::dispSvitemAdminExtraVarSetup()에서 호출
 * extend var의 언어세트를 동적으로 추가하려면 global $oLang 활성화
 * @return 
 **/
	public function getExtendVarsByModuleSrl($nModuleSrl)
	{
		//global $oLang; // to Add language variable
        $oArgs = new stdClass();
		$oArgs->sort_index = 'list_order';
		$oArgs->module_srl = $nModuleSrl;
		$oRst = executeQueryArray('svitem.getItemExtraList', $oArgs);
		if(!$oRst->toBool()) 
			return $oRst;
		unset($oArgs);
		$aDecodeVarType = ['checkbox','select','radio'];
		$aFinalExtraVar = [];
		foreach($oRst->data as $nIdx => $oExtraVar)
		{
			$nExtraSrl = $oExtraVar->extra_srl;
			$sVarType = $oExtraVar->column_type;
			$sVarName = strtolower($oExtraVar->column_name);
			$sVarTitle = $oExtraVar->column_title;
			$sVarDefaultValue = $oExtraVar->default_value;
			//$oLang->extend_vars[$sVarName] = $sVarTitle; // Add language variable
			if(in_array($sVarType, $aDecodeVarType)) 
			{
				$oExtraVar->default_value = unserialize($sVarDefaultValue); // unserialize if the data type if checkbox, select and so on
				if(!$oExtraVar->default_value[0]) 
					$oExtraVar->default_value = '';
			} 
			else 
				$oExtraVar->default_value = '';
			$aFinalExtraVar[$nExtraSrl] = $oExtraVar;
		}
		unset($oRst);
		$oRst = new BaseObject();
		$oRst->add('aFinalExtraVar', $aFinalExtraVar);
		unset( $aFinalExtraVar );
		return $oRst;
	}
/**
 * @brief item 정보를 기준으로 확장 변수 목록과 값을 취합
 * svitem.item_admin.php::_consturctExtraVars()에서 호출
 * svitem.item_consumer.php::_consturctExtraVars()에서 호출
 * @return 
 **/
	public function getExtendedVarsNameValueByItemSrl($oParam)
	{
		$aExtendVars = $this->_retrieveExtendVarValue($oParam);
		$aExtendedVars = [];
		if($aExtendVars)
		{
			foreach($aExtendVars as $nIdx => $oExtendedVar) 
				$aExtendedVars[] = new svExtenVar($oExtendedVar->module_srl, $nIdx, $oExtendedVar->column_name, $oExtendedVar->column_title, $oExtendedVar->column_type, $oExtendedVar->default_value, $oExtendedVar->description, $oExtendedVar->required, 'N', $oExtendedVar->value);
		}
		return $aExtendedVars;
	}
/**
 * @brief item_srl에 따라 확장변수 목록과 값을 결합하여 리턴
 */
	private function _retrieveExtendVarValue($oParam) 
	{
		// 모듈별 확장 변수 목록 가져오기
		$oRst = $this->getExtendVarsByModuleSrl($oParam->nModuleSrl);
		if(!$oRst->toBool()) 
			return false;//$oRst;
		$aExtendVarList = $oRst->get('aFinalExtraVar');
		unset($oRst);
		if(!$aExtendVarList)
			return false;
		$oRst = $this->_getExtraVarNameValueByItemSrl($oParam->nItemSrl);

		if(!$oRst->toBool()) 
			return false;// $oRst;
		$aExtraValueByName = $oRst->get('aExtraValueByName');
		unset($oRst);
		// 변수와 값 결합
		foreach($aExtendVarList as $srl => $item)
		{
			$sExtraVar = $item->column_name;
			$aExtendVarList[$srl]->value = $aExtraValueByName[$sExtraVar];
		}
		return $aExtendVarList;
	}
/**
 * @brief
 * @return 
 **/
	private function _getExtraVarNameValueByItemSrl($nItemSrl)
	{
		if(!$nItemSrl)
			return new BaseObject(-1, 'msg_invalid_item_srl');
        $oArgs = new stdClass();
		$oArgs->item_srl = $nItemSrl;
		$oRst = executeQueryArray("svitem.getSvitemExtraVars",$oArgs);
		if(!$oRst->toBool()) 
			return $oRst;
		if($oRst->data) 
		{
			$aExtraValueByName = [];
			foreach($oRst->data as $nIdx => $oExtendedVar)
				$aExtraValueByName[$oExtendedVar->name] = $oExtendedVar->value;
		}
		unset($oRst);
		unset($oArgs);
		$oRst = new BaseObject();
		$oRst->add('aExtraValueByName',$aExtraValueByName);
		unset($aExtraValueByName);
		return $oRst;
	}
/**
 * @brief 추가등록폼 필드가 DB필드 혹은 이미추가된 항목가 중복되는지 체크
 * @return 중복되면 TRUE, 아니면 FALSE
 */
	private function _checkColumnName($nModulesrl, $sColumnName)
	{
		// check in reserved keywords
		if(in_array($sColumnName, array('module','act','module_srl', 'document_srl','description', 'delivery_info', 'item_srl','category_depth1','category_depth2','category_depth3','category_depth4','thumbnail_image','contents_file'))) 
			return TRUE;
		// check in extra keys
        $oArgs = new stdClass();
		$oArgs->module_srl = $nModulesrl;
		$oArgs->column_name = $sColumnName;
		$oRst = executeQuery('svitem.isExistsExtraKey', $oArgs);
		if($oRst->data->count) 
			return TRUE;
		unset($oRst);
		// check in db fields
		$oDB = &DB::getInstance();
		if($oDB->isColumnExists('svitem_items', $sColumnName))
			return TRUE;
		return FALSE;
	}
}
/**
 * @brief 확장변수 클래스
 */
class svExtenVar//NExtraItem
{
	var $module_srl = 0; //Sequence of module
	var $idx = 0; // int Index of extra variable
	var $name = 0; // string  Name of extra variable
	var $title = 0; // string  title of extra variable
	var $type = 'text'; // string Type of extra variable - text, homepage, email_address, tel, textarea, checkbox, date, select, radio, kr_zip
	var $default = null; // string[]   Default values
	var $desc = ''; // string    Description
	var $is_required = 'N'; // string  Whether required or not requred this extra variable - Y, N 
	var $search = 'N'; // string   Whether can or can not search this extra variable  Y, N
	var $value = null; // string   Value
	var $eid = ''; // string  Unique id of extra variable in module
/**
 * Constructor
 * @param int $module_srl Sequence of module
 * @param int $idx Index of extra variable
 * @param string $type Type of extra variable. text, homepage, email_address, tel, textarea, checkbox, date, sleect, radio, kr_zip
 * @param string[] $default Default values
 * @param string $desc Description
 * @param string $is_required Whether required or not requred this extra variable. Y, N
 * @param string $search Whether can or can not search this extra variable
 * @param string $value Value
 * @param string $eid Unique id of extra variable in module
 * @return void
 */
	function __construct($module_srl, $idx, $name, $title, $type = 'text', $default = null, $desc = '', $is_required = 'N', $search = 'N', $value = null, $eid = '')
	{
		if(!$idx)
			return;
		$this->module_srl = $module_srl;
		$this->idx = $idx;
		$this->name = $name;
		$this->title = $title;
		$this->type = $type;
		$this->default = $default;
		$this->desc = $desc;
		$this->is_required = $is_required;
		$this->search = $search;
		$this->eid = $eid;
		$this->setValue($value);
	}
/**
 * Sets Value
 * @param string $value The value to set
 * @return void
 */
	function setValue($value)
	{
		$this->value = $this->_getTypeValue($this->type, $value);
	}
/**
 * Returns a plain value
 * @return string Returns a value expressed in text.
 */
	function getValuePlain()
	{
		$value = $this->_getTypeValue($this->type, $this->value);
		switch($this->type)
		{
			case 'homepage' :
				return $value;
			case 'email_address' :
				return $value;
			case 'tel' :
				return sprintf('%s - %s - %s', $value[0], $value[1], $value[2]);
			case 'textarea' :
				return $value;
			case 'checkbox' :
				if(is_array($value))
					return implode(', ', $value);
				else
					return $value;
			case 'date' :
				return zdate($value, "Y-m-d");
			case 'select' :
			case 'radio' :
				if(is_array($value))
					return implode(', ', $value);
				else
					return $value;
			case 'kr_zip' :
				if(is_array($value))
					return implode(' ', $value);
				else
					return $value;
			// case 'text' :
			default :
				return $value;
		}
	}
/**
 * Returns a given value converted based on its type
 * @param string $type Type of variable
 * @param string $value Value
 * @return string Returns a converted value
 */
	function _getTypeValue($type, $value)
	{
		if(!isset($value))
			return;

		switch($type)
		{
			case 'homepage' :
				if($value && !preg_match('/^([a-z]+):\/\//i', $value))
					$value = 'http://' . $value;
				return htmlspecialchars($value);
			case 'tel' :
				if(is_array($value))
					$values = $value;
				elseif(strpos($value, '|@|') !== FALSE)
					$values = explode('|@|', $value);
				elseif(strpos($value, ',') !== FALSE)
					$values = explode(',', $value);
				$values[0] = $values[0];
				$values[1] = $values[1];
				$values[2] = $values[2];
				return $values;
			case 'checkbox' :
			case 'radio' :
			case 'select' :
				if(is_array($value))
					$values = $value;
				elseif(strpos($value, '|@|') !== FALSE)
					$values = explode('|@|', $value);
				elseif(strpos($value, ',') !== FALSE)
					$values = explode(',', $value);
				else
					$values = array($value);

				for($i = 0; $i < count($values); $i++)
				{
					$values[$i] = htmlspecialchars(trim($values[$i]));
				}
				return $values;
			case 'kr_zip' :
				if(is_array($value))
					$values = $value;
				elseif(strpos($value, '|@|') !== false)
					$values = explode('|@|', $value);
				elseif(strpos($value, ',') !== false)
					$values = explode(',', $value);
				else
					$values = array($value);
				return $values;
			case 'date' :
				return str_replace('-', '', $value);
			//case 'email_address' :
			//case 'text' :
			//case 'textarea' :
			default :
				return htmlspecialchars($value);
		}
	}
/**
 * Returns a value for HTML
 * @return string Returns a value expressed in HTML.
 */
	function getValueHTML()
	{
		$value = $this->_getTypeValue($this->type, $this->value);
		switch($this->type)
		{
			case 'homepage' :
				return ($value) ? (sprintf('<a href="%s" target="_blank">%s</a>', $value, strlen($value) > 60 ? substr($value, 0, 40) . '...' . substr($value, -10) : $value)) : "";
			case 'email_address' :
				return ($value) ? sprintf('<a href="mailto:%s">%s</a>', $value, $value) : "";
			case 'tel' :
				return sprintf('%s - %s - %s', $value[0], $value[1], $value[2]);
			case 'textarea' :
				return nl2br($value);
			case 'checkbox' :
				if(is_array($value))
					return implode(', ', $value);
				else
					return $value;
			case 'date' :
				return zdate($value, "Y-m-d");
			case 'select' :
			case 'radio' :
				if(is_array($value))
					return implode(', ', $value);
				else
					return $value;
			case 'kr_zip' :
				if(is_array($value))
					return implode(' ', $value);
				else
					return $value;
			// case 'text' :
			default :
				return $value;
		}
	}
/**
 * Returns a form based on its type
 * @return string Returns a form html.
 */
	function getFormHTML($include_desc=TRUE)
	{
		static $id_num = 1000;
		$type = $this->type;
		$value = $this->_getTypeValue($this->type, $this->value);
		$default = $this->_getTypeValue($this->type, $this->default);
		$column_name = $this->name;
		$tmp_id = $column_name . '-' . $id_num++;

		$buff = '';
		switch($type)
		{
			// Homepage
			case 'homepage' :
				$buff .= '<input type="text" name="' . $column_name . '" value="' . $value . '" class="homepage" />';
				break;
			// Email Address
			case 'email_address' :
				$buff .= '<input type="text" name="' . $column_name . '" value="' . $value . '" class="email_address" />';
				break;
			// Phone Number
			case 'tel' :
				$buff .= '<input type="text" name="' . $column_name . '[]" value="' . $value[0] . '" size="4" maxlength="4" class="tel" />' .
						'<input type="text" name="' . $column_name . '[]" value="' . $value[1] . '" size="4" maxlength="4" class="tel" />' .
						'<input type="text" name="' . $column_name . '[]" value="' . $value[2] . '" size="4" maxlength="4" class="tel" />';
				break;
			// textarea
			case 'textarea' :
				$buff .= '<textarea name="' . $column_name . '" rows="8" cols="42">' . $value . '</textarea>';
				break;
			// multiple choice
			case 'checkbox' :
				$buff .= '<ul>';
				foreach($default as $v)
				{
					if($value && in_array(trim($v), $value))
						$checked = ' checked="checked"';
					else
						$checked = '';
					// Temporary ID for labeling
					$tmp_id = $column_name . '-' . $id_num++;
					$buff .='<li><label for="' . $tmp_id . '"><input type="checkbox" name="' . $column_name . '[]" id="' . $tmp_id . '" value="' . htmlspecialchars($v) . '" ' . $checked . ' />' . $v . '</label></li>';
				}
				$buff .= '</ul>';
				break;
			// single choice
			case 'select' :
				$buff .= '<select name="' . $column_name . '" class="select">';
				foreach($default as $v)
				{
					if($value && in_array(trim($v), $value))
						$selected = ' selected="selected"';
					else
						$selected = '';
					$buff .= '<option value="' . $v . '" ' . $selected . '>' . $v . '</option>';
				}
				$buff .= '</select>';
				break;
			// radio
			case 'radio' :
				$buff .= '<ul>';
				foreach($default as $v)
				{
					if($value && in_array(trim($v), $value))
						$checked = ' checked="checked"';
					else
						$checked = '';
					// Temporary ID for labeling
					$tmp_id = $column_name . '-' . $id_num++;
					$buff .= '<li><input type="radio" name="' . $column_name . '" id="' . $tmp_id . '" ' . $checked . ' value="' . $v . '"  class="radio" /><label for="' . $tmp_id . '">' . $v . '</label></li>';
				}
				$buff .= '</ul>';
				break;
			// date
			case 'date' :
				// datepicker javascript plugin load
				Context::loadJavascriptPlugin('ui.datepicker');
				$buff .= '<input type="hidden" name="' . $column_name . '" value="' . $value . '" />' .
						'<input type="text" id="date_' . $column_name . '" value="' . zdate($value, 'Y-m-d') . '" class="date" /> <input type="button" value="' . Context::getLang('cmd_delete') . '" id="dateRemover_' . $column_name . '" />' . "\n" .
						'<script>' . "\n" .
						'(function($){' . "\n" .
						'    $(function(){' . "\n" .
						'        var option = { dateFormat: "yy-mm-dd", changeMonth:true, changeYear:true, gotoCurrent: false,yearRange:\'-100:+10\', onSelect:function(){' . "\n" .
						'            $(this).prev(\'input[type="hidden"]\').val(this.value.replace(/-/g,""))}' . "\n" .
						'        };' . "\n" .
						'        $.extend(option,$.datepicker.regional[\'' . Context::getLangType() . '\']);' . "\n" .
						'        $("#date_' . $column_name . '").datepicker(option);' . "\n" .
						'		$("#dateRemover_' . $column_name . '").click(function(){' . "\n" .
						'			$(this).siblings("input").val("");' . "\n" .
						'			return false;' . "\n" .
						'		})' . "\n" .
						'    });' . "\n" .
						'})(jQuery);' . "\n" .
						'</script>';
				break;
			// address
			case "kr_zip" :
				// krzip address javascript plugin load
				Context::loadJavascriptPlugin('ui.krzip');
				$buff .=
						'<div id="addr_searched_' . $column_name . '" style="display:' . ($value[0] ? 'block' : 'none') . ';">' .
						'<input type="text" readonly="readonly" name="' . $column_name . '[]" value="' . $value[0] . '" class="address" />' .
						'<a href="#" onclick="doShowKrZipSearch(this, \'' . $column_name . '\'); return false;" class="button red"><span>' . Context::getLang('cmd_cancel') . '</span></a>' .
						'</div>' .
						'<div id="addr_list_' . $column_name . '" style="display:none;">' .
						'<select name="addr_list_' . $column_name . '"></select>' .
						'<a href="#" onclick="doSelectKrZip(this, \'' . $column_name . '\'); return false;" class="button blue"><span>' . Context::getLang('cmd_select') . '</span></a>' .
						'<a href="#" onclick="doHideKrZipList(this, \'' . $column_name . '\'); return false;" class="button red"><span>' . Context::getLang('cmd_cancel') . '</span></a>' .
						'</div>' .
						'<div id="addr_search_' . $column_name . '" style="display:' . ($value[0] ? 'none' : 'block') . '">' .
						'<input type="text" name="addr_search_' . $column_name . '" class="address" value="" />' .
						'<a href="#" onclick="doSearchKrZip(this, \'' . $column_name . '\'); return false;" class="button green"><span>' . Context::getLang('cmd_search') . '</span></a>' .
						'</div>' .
						'<input type="text" name="' . $column_name . '[]" value="' . htmlspecialchars($value[1]) . '" class="address" />' .
						'';
				break;
			// General text
			default :
				$buff .=' <input type="text" name="' . $column_name . '" value="' . ($value !== NULL ? $value : $default) . '" class="text" />';
				break;
		}
		if($this->desc && $include_desc)
			$buff .= '<p>' . htmlspecialchars($this->desc) . '</p>';
		return $buff;
	}
/**
 * @param $display_required 필수여부 출력
 */
	function getTitle($display_required=FALSE)
	{
		if($display_required) 
			if($this->is_required == 'Y') return $this->title.' <em style="color:red">*</em>';
		return $this->title;
	}
}
/* End of file ExtraItem.class.php */