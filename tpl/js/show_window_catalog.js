jQuery(function($) {
	var editForm = $('#editCategory');

	function resetEditForm() {
		editForm.find('input[name=category_srl]').val('');
		editForm.find('input[name=category_name]').val('');
		editForm.find('input[name=thumbnail_width]').val('150');
		editForm.find('input[name=thumbnail_height]').val('150');
		editForm.find('input[name=num_columns]').val('6');
		editForm.find('input[name=num_rows]').val('2');
	}

	$('a._edit').click(function() {
		var category_srl = $(this).parent().attr('id').replace(/record_/i,'');
		exec_xml(
			'svitem',
			'getSvitemAdminDisplayCategory',
			{category_srl:category_srl},
			function(ret){
				editForm.find('input[name=category_srl]').val(ret.data.category_srl);
				editForm.find('input[name=category_name]').val(ret.data.category_name);
				/*
				editForm.find('input[name=thumbnail_width]').val(ret.data.thumbnail_width);
				editForm.find('input[name=thumbnail_height]').val(ret.data.thumbnail_height);
				editForm.find('input[name=num_columns]').val(ret.data.num_columns);
				editForm.find('input[name=num_rows]').val(ret.data.num_rows);
				*/
			},
			['error','message','data']
		);
	});
	$('a._add').click(function() {
		resetEditForm();
	});

	$("#categories").sortable({ handle:'.iconMoveTo', opacity: 0.6, cursor: 'move',
			update: function(event,ui) {
				var order = jQuery(this).sortable("serialize");
				var params = new Array();
				params['order'] = order;
				var response_tags = new Array('error','message');
				exec_xml('svorder_digital', 'procSvitemAdminUpdateDCListOrder', params, function(ret_obj) { }, response_tags);
			}
		});
});

function delete_show_window_catalog(nCatalogSrl) 
{
	if (!confirm('정말 삭제하시겠습니까?')) 
		return;
	var params = new Array();
	params['mode'] = 'delete';
	params['catalog_srl'] = nCatalogSrl;
	exec_xml('svitem', 'procSvitemAdminUpdateShowWindowCatalog', params, function(ret_obj) { alert(ret_obj['message']); location.href = current_url; });
}