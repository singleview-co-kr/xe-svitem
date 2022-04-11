function layer_open(el)
{
	var temp = jQuery('#' + el);
	var bg = temp.prev().hasClass('bg');	//dimmed 레이어를 감지하기 위한 boolean 변수
	if(bg){
		jQuery('.svitem_layer').fadeIn();	//'bg' 클래스가 존재하면 레이어가 나타나고 배경은 dimmed 된다. 
	}else{
		temp.fadeIn();
	}
	// 화면의 중앙에 레이어를 띄운다.
	if (temp.outerHeight() < jQuery(document).height() ) temp.css('margin-top', '-'+temp.outerHeight()/2+'px');
	else temp.css('top', '0px');
	if (temp.outerWidth() < jQuery(document).width() ) temp.css('margin-left', '-'+temp.outerWidth()/2+'px');
	else temp.css('left', '0px');

	temp.find('a.cbtn').click(function(e){
		if(bg){
			jQuery('.svitem_layer').fadeOut(); //'bg' 클래스가 존재하면 레이어를 사라지게 한다. 
		}else{
			temp.fadeOut();
		}
		sendClickEventGaectk( 'button', 'svitem_extmall_logger_popup_close', '#' );
		e.preventDefault();
	});

	temp.find('a.ccta_btn').click(function(e){
		if(bg){
			jQuery('.svitem_layer').fadeOut(); //'bg' 클래스가 존재하면 레이어를 사라지게 한다. 
		}else{
			temp.fadeOut();
		}
		sendClickEventGaectk( 'button', 'svitem_extmall_logger_popup_outlink', '#' );
	});

	jQuery('.svitem_layer .bg').click(function(e){	//배경을 클릭하면 레이어를 사라지게 하는 이벤트 핸들러
		jQuery('.svitem_layer').fadeOut();
		e.preventDefault();
	});
}