/*
* $Id: plg_content_extravote.ini 2024 ConseilGouz $
* from joomlahill  ExtraVote
* License GNU General Public License version 3 or later; see LICENSE.txt, see LICENSE.php
*
* this js updates plg_content_extravote parameters so that leave_formatting_tags parameter is displayed right from intro text limit parameter
* It also fire content_rating and content_extravote tables synchronization
*
*/
jQuery(document).ready(function(){
	jQuery( ".clear" ).parent('fieldset').parent('.controls').parent(".control-group").css({"clear":"both"});
	jQuery( ".left" ).parent('fieldset').parent('.controls').parent(".control-group").css({"float":"left"});
	jQuery( ".left" ).parent('fieldset').parent('.controls').css({"min-width":"inherit"});
	jQuery( ".right" ).parent('fieldset').parent('.controls').parent(".control-group").css({"float":"right","text-align":"center"});

	jQuery( ".half" ).parent('fieldset').parent('.controls').parent(".control-group").css({"width":"50%"});
	jQuery( ".third" ).parent('fieldset').parent('.controls').parent(".control-group").css({"width":"33%"});
	jQuery( ".full" ).parent('fieldset').parent('.controls').parent(".control-group").css({"width":"100%"});

	jQuery( ".clear" ).parent('.controls').parent(".control-group").css({"clear":"both"});
	jQuery( ".left" ).parent('.controls').parent(".control-group").css({"float":"left"});
	jQuery( ".right" ).parent('.controls').parent(".control-group").css({"float":"right","text-align":"center"});
	jQuery( ".half" ).parent('.controls').parent(".control-group").css({"width":"50%"});
	jQuery( ".third" ).parent('.controls').parent(".control-group").css({"width":"33%"});
	jQuery( ".full" ).parent('.controls').parent(".control-group").css({"width":"100%"});

    jQuery( ".half" ).parent('fieldset').parent('.controls').css({"min-width":"inherit"});
	jQuery( ".half" ).parent('div').parent('.controls').css({"min-width":"inherit"});
    jQuery( ".half" ).parent('.controls').css({"min-width":"inherit"});

    jQuery(".alert-success.clear.half").css({"width":"100%"});
	jQuery(".half").parent(".control-group").css({"width":"50%"});
	jQuery(".clear").parent(".control-group").css({"clear":"both"});

	jQuery("input.clear").css({"clear":"both"});
    jQuery("input.full").css({"width":"100%"});
	jQuery("select.clear").parent('.controls').parent(".control-group").css({"clear":"both"});
	jQuery("select.none").parent('.controls').parent(".control-group").css({"float":"none"});

	jQuery( ".half" ).parent('.form-check').parent('.controls').parent(".control-group").css({"width":"50%"});
	jQuery( ".left" ).parent('.form-check').parent('.controls').parent(".control-group").css({"float":"left"});
	jQuery( ".right" ).parent('.form-check').parent('.controls').parent(".control-group").css({"float":"right","text-align":"center"});
    
    jQuery(".form-check-input[type='checkbox']").css({"border":"2px solid #1a5997"});

    // Sync Rating and ExtraVote tables
    const sync = document.querySelector('#jform_params_sync');
            sync.addEventListener('click', (event) => {
                if (event.srcElement.checked) {
                    goSyncAjax();
                }
    });    
	function goSyncAjax() {
        url = '?option=com_ajax&plugin=extravote&action=sync&format=raw';
		Joomla.request({
			method   : 'POST',
			url   : url,
			onSuccess: function (data, xhr) {
                console.log('sync table : '+data);
            }
        });
        
        
    }
});