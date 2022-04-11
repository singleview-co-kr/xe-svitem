function delete_item(f) {
	return procFilter(f, delete_item);
}

function completeDeleteItem(ret_obj, response_tags, callback_func_args, fo_obj) {
	alert(ret_obj['message']);
	location.href = current_url.setQuery('act','dispNstore_digitalAdminItemList');
}

(function($) {
	jQuery(function($) {
		$('.category').change(function() {
			var node_id = $('option:selected', this).val();
			var depth = $(this).attr('depth');
			depth = parseInt(depth);
			depth++;
			jQuery('input[name=category_id]').val(node_id);
			load_categories(module_srl, node_id, '#category_depth'+depth);
		});
		$('a.modalAnchor.modifyDeliveryInfo').bind('before-open.mw', function(event){
			var item_srl = $(event.target).parent().attr('id');
			//var checked = $(event.target).closest('tr').find('input:radio:checked').val();

			exec_xml(
				'svitem',
				'getSvitemAdminInsertDeliveryInfo',
				{item_srl:item_srl},
				function(ret){
					var tpl = ret.tpl.replace(/<enter>/g, '\n');
					$('#extendForm').html(tpl);
				},
				['error','message','tpl']
			);

		});

		$('a.modalAnchor.modifyOptions').bind('before-open.mw', function(event){
			var item_srl = $(event.target).parent().attr('data-item-srl');
			//var checked = $(event.target).closest('tr').find('input:radio:checked').val();

			exec_xml(
				'svitem',
				'getSvitemAdminInsertOptions',
				{item_srl:item_srl},
				function(ret){
					var tpl = ret.tpl.replace(/<enter>/g, '\n');
					$('#optionsForm').html(tpl);
				},
				['error','message','tpl']
			);

		});

		$('a.modalAnchor.modifyBundling').bind('before-open.mw', function(event){
			var item_srl = $(event.target).parent().attr('data-item-srl');

			exec_xml(
				'svitem',
				'getSvitemAdminInsertBundling',
				{item_srl:item_srl},
				function(ret){
					var tpl = ret.tpl.replace(/<enter>/g, '\n');
					$('#bundlingForm').html(tpl);
				},
				['error','message','tpl']
			);

		});

	});
}) (jQuery);
