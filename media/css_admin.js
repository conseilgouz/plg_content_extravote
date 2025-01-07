/*
* $Id: plg_content_extravote.ini 2025 ConseilGouz $
* from joomlahill  ExtraVote
* License GNU General Public License version 3 or later; see LICENSE.txt, see LICENSE.php
*
* this js updates plg_content_extravote parameters so that leave_formatting_tags parameter is displayed right from intro text limit parameter
* It also fire content_rating and content_extravote tables synchronization
*
*/
document.addEventListener('DOMContentLoaded', function() {
    sel = document.querySelector('#general');    
    if (!sel) { return; }    

    sels = sel.querySelectorAll('.control-group .control-label');
    sels.forEach(function (element) {
        element.style.width = '140px';
    });   
    
    sels = sel.querySelectorAll('.clear');
    sels.forEach(function (element) {
        element.parentNode.parentNode.parentNode.style.clear = 'both';
        element.parentNode.parentNode.style.clear = 'both';
    });
    sels = sel.querySelectorAll('input.left');
    sels.forEach(function (element) {
        element.parentNode.parentNode.style.float = 'left';
    });
    sels = sel.querySelectorAll('div.left');
    sels.forEach(function (element) {
        element.parentNode.parentNode.parentNode.style.float = 'left';
        element.parentNode.parentNode.style.float = 'left';
    });
    sels = sel.querySelectorAll('input.right');
    sels.forEach(function (element) {
        element.parentNode.parentNode.style.float = 'right';
    });

    sels = sel.querySelectorAll('div.right');
    sels.forEach(function (element) {
        element.parentNode.parentNode.parentNode.style.float = 'right';
        element.parentNode.parentNode.style.float = 'right';
    });
    
    sels = sel.querySelectorAll('div.half.radio');
    sels.forEach(function (element) {
        element.parentNode.parentNode.parentNode.style.width = '50%';
        element.parentNode.parentNode.style.width = '50%';
    });
    sels = sel.querySelectorAll('input.half');
    sels.forEach(function (element) {
        element.parentNode.parentNode.style.width = '50%';
        element.parentNode.style.width = '50%';
    });
    sels = sel.querySelectorAll('div.half:not(.radio)');
    sels.forEach(function (element) {
        element.parentNode.parentNode.parentNode.style.width = '50%';
        element.parentNode.parentNode.style.width = '50%';
        element.parentNode.style.width = '50%';
    });    

    // Sync Rating and ExtraVote tables
    const sync = document.querySelector('#jform_params_sync');
            sync.addEventListener('click', (event) => {
                if (event.srcElement.checked) {
                    goSyncAjax();
                }
    });    
	function goSyncAjax() {
        url = '?option=com_ajax&plugin=extravote&group=content&action=sync&format=raw';
		Joomla.request({
			method   : 'POST',
			url   : url,
			onSuccess: function (data, xhr) {
                console.log('sync table : '+data);
            }
        });
        
        
    }
});