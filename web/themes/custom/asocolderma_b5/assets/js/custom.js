// (function ($, Drupal, once) {
//   Drupal.behaviors.asoColDerma = {
//     attach: function (context, settings) {
//       var windowW = $(window).width();
//       once('submenu', '#block-menu-left .menu-level-0 .menu-item > span', context).forEach(function (element) {
//         if (windowW <= 1024) {
//           $(element).click(function(){
//             $('#block-menu-left .menu-dropdown').slideUp();
//             $('#block-menu-left span[data-once="submenu"]').removeClass("open");
//             $(this).next().slideToggle();
//             $(this).toggleClass('open');
//           });
//         }
//       });
//       once('link-sidebar-mobile', '.menu-lateral .link-sidebar', context).forEach(function (element) {
//         if (windowW <= 1024){
//           $(element).click(function(){
//               $('.menu-lateral .region-menu-sidebar-first').slideToggle();
//               $('.main-menu .navbar-collapse').removeClass('show');
//           });
//         }
//       });
//       once('navbar-slide-up', '.menu-lateral .navbar-toggler', context).forEach(function (element) {
//         $(element).click(function(){
//           $('.menu-lateral  .region-menu-sidebar-first').slideUp();
//         });
//       });

//       /**Change color theme */
//       $('body').once().each(function() {
//         if($(this).hasClass('user-logged-in')){
//           $(':root').css('--main-color', '#07898d');
//           $(':root').css('--main-color-50', '#f2fcff');
//           $(':root').css('--main-color-100', '#f3fdff');
//           $(':root').css('--main-color-200', '#80DDEA');
//         }

//         var congresos = document.querySelectorAll("[class*=path-congresos]");
//         if(congresos.length > 0 || $(this).hasClass('page-node-type-event')){
//           $(':root').css('--main-color', '#513490');
//           $(':root').css('--main-color-50', '#fbf9ff');
//           $(':root').css('--main-color-100', '#00c1ca');
//           $(':root').css('--main-color-200', '#80DDEA');
//         }
//       });

//       /**Searcher mobile */
//       $('.buttons-options .search-icon').once().each(function() {
//         $(this).click(function(){
//           $(this).toggleClass('open');
//           $('.menu-lateral').toggleClass('searcher-on');
//           $('.main-header').toggleClass('searcher-on');
//           $('.top-searcher .search-form ').toggleClass('show');
//           $('body').removeClass('open-searcher');
//           $('.wrapper-menus .navbar-collapse').removeClass('show');
//         });
//       });

//       $('.buttons-options .navbar-toggler').once().each(function() {
//         $(this).click(function(){
//           $('.buttons-options .search-icon').removeClass('open');
//           $('.menu-lateral').removeClass('searcher-on');
//           $('.main-header').removeClass('searcher-on');
//           $('.top-searcher .search-form ').removeClass('show');
//         });
//       });

//       /**Show message */
//       $('#modal-messages').once().each(function() {
//         var modalMessages = new bootstrap.Modal($(this));
//         modalMessages.show();
//       });

//       /**Login popup */
//       $('.login-popup-form').once().each(function() {
//         $(this).attr('data-dialog-options', '{"classes":{"ui-dialog":"login-dialog"},"drupalAutoButtons":false}');
//       });

//       /*Add class header at scroll*/
//       $(window).scroll(function(event) {
//         var scroll = $(window).scrollTop();
//         if(scroll >= 20){
//           $('header .top-menu').addClass('show-title');
//         }
//         else{
//           $('header .top-menu').removeClass('show-title');
//         }
//         if(scroll > 100 && $('.search-form').hasClass('show')){
//           $('body').addClass('open-searcher');
//         }
//         else{
//           $('body').removeClass('open-searcher');
//         }
//       });
//     }
//   };
// })(jQuery, Drupal, once);
(function ($, Drupal, once) {
  Drupal.behaviors.asoColDerma = {
    attach: function (context, settings) {
      var windowW = $(window).width();

      once('submenu', '#block-menu-left .menu-level-0 .menu-item > span', context).forEach(function (element) {
        if (windowW <= 1024) {
          $(element).click(function () {
            $('#block-menu-left .menu-dropdown').slideUp();
            $('#block-menu-left span[data-once="submenu"]').removeClass("open");
            $(this).next().slideToggle();
            $(this).toggleClass('open');
          });
        }
      });

      once('link-sidebar-mobile', '.menu-lateral .link-sidebar', context).forEach(function (element) {
        if (windowW <= 1024) {
          $(element).click(function () {
            $('.menu-lateral .region-menu-sidebar-first').slideToggle();
            $('.main-menu .navbar-collapse').removeClass('show');
          });
        }
      });

      once('navbar-slide-up', '.menu-lateral .navbar-toggler', context).forEach(function (element) {
        $(element).click(function () {
          $('.menu-lateral  .region-menu-sidebar-first').slideUp();
        });
      });

      /** Change color theme */
      once('body-color-theme', 'body', context).forEach(function (element) {
        if ($(element).hasClass('user-logged-in')) {
          $(':root').css('--main-color', '#07898d');
          $(':root').css('--main-color-50', '#f2fcff');
          $(':root').css('--main-color-100', '#f3fdff');
          $(':root').css('--main-color-200', '#80DDEA');
        }

        var congresos = document.querySelectorAll("[class*=path-congresos]");
        if (congresos.length > 0 || $(element).hasClass('page-node-type-event')) {
          $(':root').css('--main-color', '#513490');
          $(':root').css('--main-color-50', '#fbf9ff');
          $(':root').css('--main-color-100', '#00c1ca');
          $(':root').css('--main-color-200', '#80DDEA');
        }
      });

      /** Searcher mobile */
      once('search-icon', '.buttons-options .search-icon', context).forEach(function (element) {
        $(element).click(function () {
          $(this).toggleClass('open');
          $('.menu-lateral').toggleClass('searcher-on');
          $('.main-header').toggleClass('searcher-on');
          $('.top-searcher .search-form ').toggleClass('show');
          $('body').removeClass('open-searcher');
          $('.wrapper-menus .navbar-collapse').removeClass('show');
        });
      });

      once('navbar-toggler', '.buttons-options .navbar-toggler', context).forEach(function (element) {
        $(element).click(function () {
          $('.buttons-options .search-icon').removeClass('open');
          $('.menu-lateral').removeClass('searcher-on');
          $('.main-header').removeClass('searcher-on');
          $('.top-searcher .search-form ').removeClass('show');
        });
      });

      /** Show message */
      once('modal-messages', '#modal-messages', context).forEach(function (element) {
        var modalMessages = new bootstrap.Modal(element);

        modalMessages.show();

        var openAspiranteLogin =
          $(element).attr('data-open-aspirante-login') === '1';

        if (!openAspiranteLogin) {
          return;
        }

        $('#aspirante-activation-continue')
          .off('click.asocoldermaActivation')
          .on('click.asocoldermaActivation', function (event) {
            event.preventDefault();

            $(element).one('hidden.bs.modal', function () {
              var $link = $('<a>', {
                href: '/aspirante/login',
                class: 'use-ajax login-popup-form',
                'data-dialog-type': 'modal'
              });

              $('body').append($link);

              Drupal.attachBehaviors($link[0]);

              window.requestAnimationFrame(function () {
                $link.trigger('click');
              });
            });

            modalMessages.hide();
          });
      });

      /** Login popup */
      once('login-popup-form', '.login-popup-form', context).forEach(function (element) {
        $(element).attr('data-dialog-options', '{"classes":{"ui-dialog":"login-dialog"},"drupalAutoButtons":false}');
      });

      /* Add class header at scroll */
      $(window).scroll(function (event) {
        var scroll = $(window).scrollTop();
        if (scroll >= 20) {
          $('header .top-menu').addClass('show-title');
        }
        else {
          $('header .top-menu').removeClass('show-title');
        }
        if (scroll > 100 && $('.search-form').hasClass('show')) {
          $('body').addClass('open-searcher');
        }
        else {
          $('body').removeClass('open-searcher');
        }
      });
    }
  };
})(jQuery, Drupal, once);
