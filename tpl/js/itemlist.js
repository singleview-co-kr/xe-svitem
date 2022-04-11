var _g_oCategoryTree = {
	bInitialLoad:'yes',
	nModuleSrl:0
};
jQuery(document).ready(function() {
	jQuery( "#tabs" ).tabs({
		activate:function(event,ui) { 
			itemListCatalogTab.setTab(ui);
		}
	});
	jQuery(".sortable").sortable({ handle:'.iconMoveTo', opacity: 0.6, cursor: 'move',
		update: function(event,ui) {
			var order = jQuery(this).sortable("serialize");
			var params = [];
			params['order'] = order;
			var response_tags = new Array('error','message');
			exec_xml('svitem', 'procSvitemAdminUpdateDIListOrder', params, function(ret_obj) { }, response_tags);
		}
	});
	jQuery( "#btnModifyForm" ).click(function() {
		jQuery( "#list_form" ).submit();
	});
	jQuery('#list_form').submit(function() {
		var search_item_name = jQuery('#searchForm input[name=search_item_name]').val();
		if(search_item_name.length) 
			jQuery(this).append('<input type="hidden" name="search_item_name" value="' + search_item_name + '" />');
	});
});
var itemListCatalogTab = 
{
	_g_nCurTabSrl : 0,
	_g_nCurCategorySrl : 0,
	setTab : function( oUi )
	{
		this._g_nCurTabSrl = jQuery(oUi.newTab).attr('category_srl');
		this._revalidateItemListOfShowWindowTab();
	},
	setCatalog : function( nCurCategorySrl )
	{
		var _bString = isNaN(nCurCategorySrl);
		if( _bString || nCurCategorySrl == 0 )
			this._g_nCurCategorySrl = 0;
		else
			this._g_nCurCategorySrl = nCurCategorySrl;
		this._revalidateItemListOfDefaultCatalog();
	},
	_getCurMode : function()
	{
		if( typeof(this._g_nCurTabSrl)==='undefined' || this._g_nCurTabSrl == 0 )
			return {'sCurMode':'default', 'nCurSCatalogSrl':this._g_nCurCategorySrl};
		else
			return {'sCurMode':'show_window', 'nCurShowWindowTabSrl':this._g_nCurTabSrl};
	},
	inviteItems : function( nItemSrl )
	{
		var oCart = document.getElementsByName("cart");
		var aCart = [];
		var nIdx = 0;
		for(var i = 0; i < oCart.length; i++)
		{
			if(oCart[i].checked)
				aCart[nIdx++] = oCart[i].value;
		}
		if( nIdx == 0 )
		{
			alert('카테고리에 추가할 상품을 선택하세요.');
			return;
		}
		var oRst = this._getCurMode();
		
		if( oRst.sCurMode == 'default' )
			this._inviteItemsDefaultCatalog(aCart);
		if( oRst.sCurMode == 'show_window' )
			this._addItemsIntoShowWindowTab(aCart);
		
	},
	withdrawItemFromDefaultCatalog : function( nItemSrl )
	{
		var params = [];
		params['module_srl'] =  _g_oCategoryTree.nModuleSrl;
		params['mode'] = 'withdraw';
		params['item_srl'] = nItemSrl;
		exec_xml('svitem', 'procSvitemAdminUpdateItemDefaultCatalogInfo', params,itemListCatalogTab._revalidateItemListOfDefaultCatalog);
	},
	withdrawItemFromShowWindowTab : function( nItemSrl )
	{
		var params = [];
		params['module_srl'] =  _g_oCategoryTree.nModuleSrl;
		params['mode'] = 'withdraw';
		params['show_window_tab_srl'] = itemListCatalogTab._g_nCurTabSrl;
		params['item_srl'] = nItemSrl;
		exec_xml('svitem', 'procSvitemAdminUpdateItemShowWindowTab', params,itemListCatalogTab._revalidateItemListOfShowWindowTab);
	},
	_inviteItemsDefaultCatalog : function(aCart)
	{
		var params = [];
		params['module_srl'] = _g_oCategoryTree.nModuleSrl;
		params['mode'] = 'invite';
		params['item_srls'] = aCart;
		params['catalog_node_srl'] = this._g_nCurCategorySrl;
		var response_tags = ['error','message','data','notice'];
		exec_xml('svitem', 'procSvitemAdminUpdateItemDefaultCatalogInfo', params, function(ret_obj) 
		{
			if( typeof(ret_obj.notice) === 'string' )
				alert(ret_obj.notice);
			itemListCatalogTab._revalidateItemListOfDefaultCatalog();
		}, response_tags);

	},
	_addItemsIntoShowWindowTab : function(aCart)
	{
		var params = [];
		params['item_srls'] = aCart;
		params['module_srl'] = _g_oCategoryTree.nModuleSrl;
		params['mode'] = 'invite';
		params['show_window_srl'] = this._g_nCurTabSrl;
		exec_xml('svitem', 'procSvitemAdminUpdateItemShowWindowTab', params, itemListCatalogTab._revalidateItemListOfShowWindowTab);
	},
	_revalidateItemListOfShowWindowTab : function()
	{
		if( typeof(itemListCatalogTab._g_nCurTabSrl)==='undefined' || itemListCatalogTab._g_nCurTabSrl == 0 )
			return;
		var params = [];
		var responses = ['error','message','data'];
		params['module_srl'] =  _g_oCategoryTree.nModuleSrl;
		params['show_window_tab_srl'] = itemListCatalogTab._g_nCurTabSrl;
		exec_xml('svitem', 'getSvitemAdminShowWindowItemListAjax', params, function(ret_obj) 
		{
			itemListCatalogTab._drawItemListOfShowWindowTab(ret_obj);
		}, responses);
	},
	_revalidateItemListOfDefaultCatalog : function()
	{
		if( typeof(this._g_nCurTabSrl)!=='undefined' && this._g_nCurTabSrl != 0 )
			return;
		jQuery('#tabs0-itemlist').empty();
		var params = [];
		params['module_srl'] = _g_oCategoryTree.nModuleSrl;
		params['category_srl'] = itemListCatalogTab._g_nCurCategorySrl;
		var response_tags = ['error','message','data'];
		exec_xml('svitem', 'getSvitemAdminItemListAjax', params, function(ret_obj) 
		{
			itemListCatalogTab._drawItemListOfCatalog(ret_obj);
		}, response_tags);
	},
	_drawItemListOfShowWindowTab : function( aRst )
	{
		$list_tabs = jQuery('#tabs-'+itemListCatalogTab._g_nCurTabSrl);
		if($list_tabs.length == 0)
			$list_tabs = jQuery('#tabs-0');
		
		$list = jQuery('> ul',$list_tabs).empty();
		if (aRst['data']) 
		{
			var _aRst = aRst['data']['item'];
			if (!jQuery.isArray(_aRst)) 
				_aRst = new Array(_aRst);
			var _sHtml = '';
			for (var i = 0; i < _aRst.length; i++)
			{
				_sHtml = '<li id="record_'+_aRst[i].item_srl+'"><span class="iconMoveTo"></span>';
				_sHtml += '&nbsp;<span>'+_aRst[i].item_name+'</span><a href="#" class="delete" onclick="itemListCatalogTab.withdrawItemFromShowWindowTab('+_aRst[i].item_srl+'); return false;">제거</a></li>';
				$list.append(_sHtml);
			}
		}
	},
	_drawItemListOfCatalog : function( aRst )
	{
		if (aRst['data']) 
		{
			var _bErasable = this._g_nCurCategorySrl==0?false:true;
			var _aRst = aRst['data']['item'];
			if (!jQuery.isArray(_aRst)) // translate single node data into array
				_aRst = new Array(_aRst);
			var _sHtml = '';
			for (var i = 0; i < _aRst.length; i++) 
			{
				_sHtml = '<li id="record_' + _aRst[i].item_srl + '"><span class="iconMoveTo">&uarr;</span><span>' + _aRst[i].item_name + '</span>';
				if( _bErasable )
					_sHtml += '&nbsp;<a href="#" class="delete" onClick="itemListCatalogTab.withdrawItemFromDefaultCatalog(' + _aRst[i].item_srl + '); return false;">제거</a>';
				_sHtml += '</li>';
				jQuery('#tabs0-itemlist').append(_sHtml);
			}
		}
	}
}

// https://www.jstree.com/docs/config/
function displayTree(nModuleSrl, sTreeId)
{
	_g_oCategoryTree.nModuleSrl = nModuleSrl;
	jQuery(sTreeId).jstree({
		"core" : {
			"animation" : 0,
			"check_callback" : function (operation, node, node_parent, node_position, more) {
					// operation can be 'create_node', 'rename_node', 'delete_node', 'move_node', 'copy_node' or 'edit'
					// in case of 'rename_node' node_position is filled with the new node name
					return _checkContextmenuCb(operation, node, node_parent);
					//return ( operation == 'delete_node' && node_parent.parent =='#' )? false:true;
				},
			"themes" : { "stripes" : true },
			'data' : {
				"url" : "./",
				"dataType" : "json",
				"data" : function (node) {
					if( _g_oCategoryTree.bInitialLoad == 'yes' )
					{
						_g_oCategoryTree.bInitialLoad = 'no';
                        nNodeSrl = 0;
                    }
					if(node.id !== '#')
						nNodeSrl = node.id ;//oData.attr('node_id');

					// the result is fed to the AJAX request `data` option
                    return { 
                        module : 'svitem',
						act : 'getSvitemAdminCatalogAjax',
						category_node_srl : nNodeSrl,
						module_srl : _g_oCategoryTree.nModuleSrl
                    };
				}
			}
		},
		"plugins" : [
			"contextmenu", "dnd", "cookies", "state", "types"
		],
		"types" : {
			"#" : {
				"max_children" : 1,
				"max_depth" : 4,
				"valid_children" : ["root"]
			},
			"root" : {
				"icon" : "/static/3.3.4/assets/images/tree_icon.png",
				"valid_children" : ["default"]
			},
			"default" : {
				"valid_children" : ["default","file"]
			},
			"file" : {
				"icon" : "glyphicon glyphicon-file",
				"valid_children" : []
			}
		}
	}).bind("create_node.jstree", function (e, data) {
		jQuery.ajax({
			type: "POST",
			dataType: "json",
			contentType: "application/json; charset=utf-8",
			url : "./", 
				data : { 
				module : "svitem",
				mode : 'insert',
				act : "procSvitemAdminUpdateCatalogNodeAjax",
				module_srl : _g_oCategoryTree.nModuleSrl,
				parent_srl : data.parent
			}, 
			success : function(r) {
				if(r.error == -1) {
					// jstree v3.0 does not implement rollback method
					//jQuery.jstree.rollback(data.rlbk);
					alert(r.message);
					//setTimeout(function(){location.reload();},2000); 
				}
				else
				{
					// jstree v3.0 does not implement rollback method for create temp node
					setTimeout(function(){location.reload();},0);
					//jQuery(data.node).attr('id', r.category_node_srl);
				}
			}
		});
    }).bind("delete_node.jstree", function (e, data) {
		var nCategoryNodeSrl = data.node.id;
		jQuery.ajax({
			type: "POST",
			dataType: "json",
			contentType: "application/json; charset=utf-8",
			url : "./", 
				data : { 
				module : "svitem",
				mode : 'delete',
				act : "procSvitemAdminUpdateCatalogNodeAjax",
				module_srl : _g_oCategoryTree.nModuleSrl,
				category_node_srl : nCategoryNodeSrl
			}, 
			success : function(r) {
				if(r.error == -1) {
					// jstree v3.0 does not implement rollback method
					//jQuery.jstree.rollback(data.rlbk);
					alert(r.message);
					setTimeout(function(){location.reload();},2000); 
				}
			}
		});
    })
    .bind("rename_node.jstree", function (e, data) {
		var nCategoryNodeSrl = data.node.id;
        var sCategoryName = data.node.text;
		jQuery.ajax({
			type: "POST",
			dataType: "json",
			contentType: "application/json; charset=utf-8",
			url : "./", 
				data : { 
				module : "svitem",
				mode : 'rename',
				act : "procSvitemAdminUpdateCatalogNodeAjax",
				module_srl : _g_oCategoryTree.nModuleSrl,
				category_node_srl : nCategoryNodeSrl,
				node_name : sCategoryName
			}, 
			success : function(r) {
				if(r.error == -1) {
					// jstree v3.0 does not implement rollback method
					//jQuery.jstree.rollback(data.rlbk);
					alert(r.message);
					setTimeout(function(){location.reload();},2000); 
				}
				else
				{
					//jQuery(data.node).attr('text', r.category_name);
				}
			}
		});
	})
	.bind("move_node.jstree", function (e, data) {
 console.log('move_node');
		data.rslt.o.each(function (i) {
            var node_id = jQuery(this).attr("node_id");
            var parent_id = data.rslt.np.attr("node_id");
            var target_id = data.rslt.or.attr("node_id");
			var position = 'next';
			if (!target_id) {
				target_id = data.rslt.r.attr("node_id");
				position = 'prev';
			}
			console.log(data.rslt);

            jQuery.ajax({
                type: 'POST',
                dataType: "json",
                contentType: "application/json; charset=utf-8",
                async : false,
                url: "./",
                data : { 
                    module : "svitem"
                    , act : "procSvitemAdminMoveCatalog"
                    , node_id : node_id
                    , parent_id : parent_id
                    , target_id : target_id
                    , position : position
                },
                success : function (r) {
                    if(r.error == -1) {
                        jQuery.jstree.rollback(data.rlbk);
                    } else {
						//console.log(data.rslt);
                        jQuery(data.rslt.oc).attr("id", "node_" + r.id);
                        if(data.rslt.cy && jQuery(data.rslt.oc).children("UL").length) {
                            data.inst.refresh(data.inst._get_parent(data.rslt.oc));
                        }
                    }
                }
            });
        });
	}).bind("select_node.jstree", function(e, data) {
		itemListCatalogTab.setCatalog(data.node.id);
	});   
}
function _checkContextmenuCb(sOperation, oNode, oNodeParent)
{
	switch( sOperation )
	{
		case 'delete_node':
			if( oNodeParent.id == '#' )
				return false;
			break;
	}
	return true;
}