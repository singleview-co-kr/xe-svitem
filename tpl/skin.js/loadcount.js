jQuery(document).ready(function (){

	loaded_count();

});

function loaded_count()
{
	var params = new Array();
	var respons = ['item_count'];

	exec_xml('svcart', 'getSvcartCartItems', params, function(ret_obj) {
		jQuery("#count_cart_items").html(ret_obj['item_count']);
	},respons);

	if(jQuery("#n_member_srl").val())
	{
		exec_xml('svcart', 'getSvcartFavoriteItems', params, function(ret_obj) {
			jQuery("#count_favorites_items").html(ret_obj['item_count']);
		},respons);
	}
}
