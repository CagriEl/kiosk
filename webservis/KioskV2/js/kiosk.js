function backDoWork() {
    //history.go(-1);
    yukleniyor.goster();
    window.location.href = '/Belsis-Net/kioskv2/default.aspx'
}

function UrlExists(url, cb) {
    jQuery.ajax({
        url: url,
        dataType: 'text',
        type: 'GET',
        complete: function (xhr) {
            if (typeof cb === 'function')
                cb.apply(this, [xhr.status]);
        }
    });
}

function uiHataGoster(baslik, mesaj) {
    sweetAlert(baslik, mesaj, "error");
}

function uiBilgiVer(baslik, mesaj) {
    swal(baslik, mesaj);
}

function uiBasariliGoster(baslik, mesaj) {
    swal(baslik, mesaj, "success");
}

var yukleniyor = yukleniyor || (function ($) {
    'use strict';

    var $dialog = $(
		'<div class="modal fade" data-backdrop="static" data-keyboard="false" tabindex="-1" role="dialog" aria-hidden="true" style="padding-top:15%; overflow-y:visible;">' +
		'<div class="modal-dialog modal-m">' +
		'<div class="modal-content">' +
			'<div class="modal-header"><h3 style="margin:0;"></h3></div>' +
			'<div class="modal-body">' +
				'<div class="progress progress-striped active" style="margin-bottom:0;"><div class="progress-bar" style="width: 100%"></div></div>' +
			'</div>' +
		'</div></div></div>');

    return {
        goster: function (message, options) {
            if (typeof options === 'undefined') {
                options = {};
            }
            if (typeof message === 'undefined') {
                message = 'Lütfen Bekleyiniz..';
            }
            var settings = $.extend({
                dialogSize: 'm',
                progressType: '',
                onShow: null,
                onHide: null 
            }, options);

            $dialog.find('.modal-dialog').attr('class', 'modal-dialog').addClass('modal-' + settings.dialogSize);
            $dialog.find('.progress-bar').attr('class', 'progress-bar');
            if (settings.progressType) {
                $dialog.find('.progress-bar').addClass('progress-bar-' + settings.progressType);
            }
            $dialog.find('h3').text(message);            
            if (typeof settings.onShow === 'function') {
                $dialog.off('shown.bs.modal').on('shown.bs.modal', function (e) {
                    settings.onShow.call($dialog);
                });
            }
            if (typeof settings.onHide === 'function') {
                $dialog.off('hidden.bs.modal').on('hidden.bs.modal', function (e) {
                    settings.onHide.call($dialog);
                });
            }
            $dialog.modal();
        },
        gizle: function () {
            $dialog.modal('hide');
        }
    };

})(jQuery);

var baglaniyor = baglaniyor || (function ($) {
    'use strict';

    var $dialog = $(
		'<div class="modal fade" data-backdrop="static" data-keyboard="false" tabindex="-1" role="dialog" aria-hidden="true" style="padding-top:15%; overflow-y:visible;">' +
		'<div class="modal-dialog modal-m">' +
		'<div class="modal-content">' +
			'<div class="modal-header"><h3 style="margin:0;"></h3></div>' +
			'<div class="modal-body">' +
				'<div class="progress progress-striped active" style="margin-bottom:0;"><div class="progress-bar" style="width: 100%"></div></div>' +
			'</div>' +
		'</div></div></div>');

    return {
        goster: function (message, options) {
            if (typeof options === 'undefined') {
                options = {};
            }
            if (typeof message === 'undefined') {
                message = 'Sunucularımızda Bakım Yapıldığından Geçici Olarak Hizmet Veremiyoruz..';
            }
            var settings = $.extend({
                dialogSize: 'm',
                progressType: '',
                onShow: null,
                onHide: null
            }, options);

            $dialog.find('.modal-dialog').attr('class', 'modal-dialog').addClass('modal-' + settings.dialogSize);
            $dialog.find('.progress-bar').attr('class', 'progress-bar');
            if (settings.progressType) {
                $dialog.find('.progress-bar').addClass('progress-bar-' + settings.progressType);
            }
            $dialog.find('h3').text(message);
            if (typeof settings.onShow === 'function') {
                $dialog.off('shown.bs.modal').on('shown.bs.modal', function (e) {
                    settings.onShow.call($dialog);
                });
            }
            if (typeof settings.onHide === 'function') {
                $dialog.off('hidden.bs.modal').on('hidden.bs.modal', function (e) {
                    settings.onHide.call($dialog);
                });
            }
            $dialog.modal();
        },
        gizle: function () {
            $dialog.modal('hide');
        }
    };

})(jQuery);

$(function () {

    //sağ tuş iptali
    document.oncontextmenu = document.body.oncontextmenu = function () { return false; }

    $('.innerFrame').height(window.innerHeight - ($('.header').outerHeight() + $('.footer').outerHeight()));

    $('.innerFrame').parents('body').addClass('clear-padding');

    if (document.getElementsByClassName('innerFrame')[0]) {
        yukleniyor.goster();
    }

    $('.innerFrame').load(function () {
        yukleniyor.gizle();
    });

    
    /*Sanal Klavye Ayarları 14.06.2017*/
    /*
    $('#kartAdiSoyadi').keyboard({ placement: 'top' }, {
        layout: [
            [['q', 'Q'],
                ['w', 'W'],
                ['e', 'E'],
                ['r', 'R'],
                ['t', 'T'],
                ['y', 'Y'],
                ['u', 'U'],
                ['ı', 'I'],


                ['o', 'O'],
                ['p', 'P'],
                ['ğ', 'Ğ'],
                ['ü', 'Ü'],
                ['shift', 'shift'],
                ['a', 'A'],
                ['s', 'S'],
                ['d', 'D'],
                ['f', 'F'],
                ['g', 'G'],
                ['h', 'H'],
                ['j', 'J'],
                ['k', 'K'],
                ['l', 'L'],
                ['ş', 'Ş'],
                ['i', 'I'],
                ['z', 'Z'],
                ['x', 'X'],
                ['c', 'C'],
                ['v', 'V'],
                ['b', 'B'],
                ['n', 'N'],
                ['m', 'M'],
                ['ö', 'Ö'],
                ['ç', 'Ç']

            ],
            [['del', 'del'], ['space', 'space'], ['del', 'del'], ['enter', 'enter'],['tab','tab']]

        ]
    });
    $('#kartNo').keyboard({ type: 'numpad' });
    $('#kartCCV').keyboard({ type: 'numpad' });
    */
});