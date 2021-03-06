/**
 * Admin interface functionalities
 * @package zork
 * @subpackage admin
 * @author David Pozsar <david.pozsar@megaweb.hu>
 */
( function ( global, $, js )
{
    "use strict";

    if ( typeof js.admin !== "undefined" )
    {
        return;
    }

    /**
     * @class User module
     * @constructor
     * @memberOf Zork
     */
    global.Zork.Admin = function ()
    {
        this.version = "1.0";
        this.modulePrefix = [ "zork", "admin" ];
    };

    global.Zork.prototype.admin = new global.Zork.Admin();

    var papr = function ( prop ) {
            return $(
                '.paragraph-container' +
                '[data-paragraph-id]' +
                '[data-paragraph-properties]' +
                '[data-paragraph-id!=""]' +
                '[data-paragraph-properties~="' + prop + '"]'
            ).first();
        },
        offset = function ( elem, type ) {
            elem = $( elem );
            var offs = elem.offset();

            switch ( type )
            {
                case "top":
                case "left":
                    return offs[type];

                case "right":
                    return $( global.document ).width() - offs.left - elem.width();

                case "bottom":
                    return $( global.document ).height() - offs.top - elem.height();
            }
        },
        layout  = null,
        content = null,
        menus   = [],
        corners = {
            "right" : "left",
            "left"  : "right"
        };

    /**
     * Admin-menu element
     *
     * @memberOf Zork.Admin
     */
    global.Zork.Admin.prototype.menu = function ( element )
    {
        js.style( "/styles/scripts/adminmenu.css" );
        element = $( element );
        menus.push( element[0] );

        var display     = element.data( "jsAdminmenuState" ) == "open",
            position    = element.data( "jsAdminmenuPosition" ) || "left",
            editMode    = element.data( "jsAdminmenuEditmode" ) || "none",
            editCont    = "content" == editMode,
            editLay     = "layout"  == editMode,
            contSlide   = $( "<div />" ),
            contSlideWrapper = $( "<div />" ).hide(),
            laySlide    = $( "<div />" ),
            laySlideWrapper = $( "<div />" ).hide(),
            yes         = "&#x2714;",
            no          = "&#x2718;",
            menu        = element.children( "ul" ),
            header      = $( "<div />" ),
            text        = $( "<span />" ).addClass( "text" )
                                         .html( element.attr( "title" ) )
                                         .hide(),
            icon        = function () {
                switch ( position )
                {
                    case "right":
                        return display
                            ? "ui-icon-circle-triangle-e"
                            : "ui-icon-circle-triangle-w";

                    case "left":
                    default:
                        return display
                            ? "ui-icon-circle-triangle-w"
                            : "ui-icon-circle-triangle-e";
                }
            },
            changePos   = function ( pos ) {
                var corner = corners[position] || "all";

                header.removeClass( "ui-corner-" + corner );
                element.removeClass( "js-adminmenu-" + position )
                       .removeClass( "ui-corner-" + corner );

                position = pos;
                corner   = corners[position] || "all";

                element.data( "jsAdminmenuPosition", position );
                toggle.button( "option", "icons", { "primary": icon() } );
                header.addClass( "ui-corner-" + corner );
                element.addClass( "js-adminmenu-" + position )
                       .addClass( "ui-corner-" + corner );
            },
            openRpc     = js.core.rpc( {
                              "method": "Grid\\User\\Model\\AdminMenuSettings::open",
                              "callback": function () {}
                          } ),
            posRpc      = js.core.rpc( {
                              "method": "Grid\\User\\Model\\AdminMenuSettings::position",
                              "callback": function () {}
                          } ),
            editRpc     = js.core.rpc( {
                              "method": "Grid\\User\\Model\\AdminMenuSettings::editMode",
                              "callback": function () {}
                          } ),
            toggle      = $( "<button></button>" )
                            .addClass( "toggle" )
                            .button( {
                                "text": false,
                                "icons": { "primary": "" }
                            } );

        element.attr( "title", "" )
               .addClass( "js-adminmenu ui-widget ui-widget-content" )
               .prepend( header.append( text ).append( toggle ) );

        header.addClass( "js-adminmenu-header ui-widget-header" );
        changePos( position );

        menu.removeClass( "ui-helper-hidden" )
            .menu()
            .addClass( "ui-helper-clearfix js-adminmenu-menu" );

        if ( display )
        {
            text.show();
            menu.show();
        }

        var menuOriginWidth;

        setTimeout( function() {
            menuOriginWidth = parseInt( menu.width() );

            if ( ! display )
            {
                menu.css( {
                        "width": "32px",
                        "overflow": "hidden"
                    } )
                    .find( "span.status" )
                        .hide();
            }
        }, 400 );

        toggle.click( function () {
            openRpc( display = ! display );
            toggle.button( "option", "icons", { "primary": icon() } );

            if ( display )
            {
                text.show( "fast" );
                menu.css( "overflow", "visible" )
                    .animate( {
                        "width": menuOriginWidth + "px"
                    }, {
                        "duration": "fast",
                        "complete": function () {
                            menu.css( "width", "auto" );
                            menuOriginWidth = Math.max(
                                menuOriginWidth,
                                parseInt( menu.width() )
                            );
                        }
                    } )
                    .find( "span.status" )
                        .show( "fast" );
            }
            else
            {
                text.hide( "fast" );
                menu.css( {
                        "overflow": "hidden",
                        "width": menuOriginWidth + "px"
                    } )
                    .animate( {
                        "width": "32px"
                    }, "fast" )
                    .find( "span.status" )
                        .hide( "fast" );
            }
        } );

        menu.find( "> li > .file-manager" )
            .click( function ( event ) {
                js.require( "js.core.pathselect", function ( pathselect ) {
                    pathselect( {
                        "return": false
                    } );
                } );

                event.preventDefault();
                return false;
            } );

        var editContNode = menu.find( "> li > .edit-content" ),
            editContPara = menu.find( "> li > .edit-content-paragraph" ),
            editLayNode  = menu.find( "> li > .edit-layout" );

        editContNode.addClass( "ui-state-disabled" )
                    .parent( "li" ).append( '<span class="status" />' );

        editLayNode.addClass( "ui-state-disabled" )
                   .parent( "li" ).append( '<span class="status" />' );

        js.admin.menu.refresh();

        contSlideWrapper.addClass( "ui-collapsible-content" )
                        .appendTo( editContNode.parent( "li" ) );

        contSlide.appendTo( contSlideWrapper )
                 .slider( {
                        "min": 0,
                        "max": 20,
                        "slide": function ( event, ui ) {
                            if ( editCont )
                            {
                                js.require( "js.paragraph" )
                                  .padding( content, ui.value );
                            }
                        }
                    } );

        laySlideWrapper.addClass( "ui-collapsible-content" )
                       .appendTo( editLayNode.parent( "li" ) );

        laySlide.appendTo( laySlideWrapper )
                .slider( {
                    "min": 0,
                    "max": 20,
                    "slide": function ( event, ui ) {
                        if ( editLay )
                        {
                            js.require( "js.paragraph" )
                              .padding( layout, ui.value );
                        }
                    }
                } );

        contSlideWrapper.parent( "li" )
                        .addClass( "ui-menu-item-collapsible")
                        .addClass( "ui-state-collapsed" );

        laySlideWrapper.parent( "li" )
                       .addClass( "ui-menu-item-collapsible")
                       .addClass( "ui-state-collapsed" );

        var editContOn = function () {
                editCont = true;

                contSlide.slider( "value", 0 );
                contSlideWrapper.show( "fast" )
                                .parent( "li" )
                                .removeClass( "ui-state-collapsed" );

                editContNode.removeClass( "ui-state-disabled" )
                            .find( ".status" )
                            .html( yes );
            },
            editLayOn = function () {
                editLay = true;

                laySlide.slider( "value", 0 );
                laySlideWrapper.show( "fast" )
                               .parent( "li" )
                               .removeClass( "ui-state-collapsed" );

                editLayNode.removeClass( "ui-state-disabled" )
                           .find( ".status" )
                           .html( yes );
            },
            editContOff = function () {
                editCont = false;

                contSlideWrapper.hide( "fast" )
                                .parent( "li" )
                                .addClass( "ui-state-collapsed" );

                contSlide.slider( "value", 0 );

                editContNode.addClass( "ui-state-disabled" )
                            .find( ".status" )
                            .html( no );
            },
            editLayOff = function () {
                editLay = false;

                laySlideWrapper.hide( "fast" )
                               .parent( "li" )
                               .addClass( "ui-state-collapsed" );

                laySlide.slider( "value", 0 );

                editLayNode.addClass( "ui-state-disabled" )
                           .find( ".status" )
                           .html( no );
            },
            switchContEdit = function () {
                js.require( "js.paragraph" )
                  .edit( content );
            },
            switchLayEdit = function () {
                if ( content && content.length )
                {
                    content.find( ".paragraph-container" )
                           .andSelf()
                           .addClass( "paragraph-edit-disabled" );
                }

                js.require( "js.paragraph" )
                  .edit( layout );
            },
            switchContReset = function () {
                js.require( "js.paragraph" )
                  .reset( content );
            },
            switchLayReset = function () {
                js.require( "js.paragraph" )
                  .reset( layout );

                if ( content && content.length )
                {
                    content.find( ".paragraph-container.paragraph-edit-disabled" )
                           .andSelf()
                           .removeClass( "paragraph-edit-disabled" );
                }
            };

        editContNode.click( function () {
            if ( ! content || ! content.length )
            {
                return false;
            }

            if ( editLay )
            {
                editLayOff();
                switchLayReset();
            }

            if ( editCont )
            {
                editRpc( "none" );
                editContOff();
                switchContReset();
            }
            else
            {
                editRpc( "content" );
                editContOn();
                switchContEdit();
            }

            return false;
        } );

        if ( editCont )
        {
            setTimeout( function () {
                editContOn();
                switchContEdit();
            }, 1 );
        }

        editLayNode.click( function () {
            if ( editCont )
            {
                editContOff();
                switchContReset();
            }

            if ( editLay )
            {
                editRpc( "none" );
                editLayOff();
                switchLayReset();
            }
            else
            {
                editRpc( "layout" );
                editLayOn();
                switchLayEdit();
            }

            return false;
        } );

        if ( editLay )
        {
            setTimeout( function () {
                editLayOn();
                switchLayEdit();
            }, 1 );
        }

        editContPara.click( function ( event ) {
            if ( content.length )
            {
                js.require( "js.paragraph" )
                  .dashboard( content );
            }

            event.preventDefault();
        } );

        var changeParam = content.length
                ? "/" + content.data( "paragraphId" ) : "",
            customizeParam = "/" + layout.data( "paragraphId" );

     /* if ( content.length )
        {
            customizeParam += "/";
            customizeParam += content.data( "paragraphId" );
        } */

        changeParam += "?returnUri=";
        changeParam += encodeURIComponent( global.location.href );

        customizeParam += "?returnUri=";
        customizeParam += encodeURIComponent( global.location.href );

        menu.find( "> li > .change-layout" )
            .click( function () {
                var tabh    = $( "<ul />" ),
                    tabs    = $( "<div />" ).append( tabh ),
                    close;

                global.Zork.Admin.prototype.menu.close = function () {
                    close();
                    global.Zork.Admin.prototype.menu.close = function () {};
                };

                tabh.append( $( "<li />").append( $( "<a />", {
                    "href": "/app/" + js.core.userLocale +
                            "/paragraph/change-layout" + changeParam,
                    "text": js.core.translate(
                                "admin.menu.changeLayout.my",
                                js.core.userLocale
                            )
                } ) ) );

                tabh.append( $( "<li />" ).append( $( "<a />", {
                    "href": "/app/" + js.core.userLocale +
                            "/paragraph/import-layout" + changeParam,
                    "text": js.core.translate(
                                "admin.menu.changeLayout.predefined",
                                js.core.userLocale
                            )
                } ) ) );

             /* tabh.append( $( "<li />", ).append( $( "a", {
                    "href": "/app/" + js.core.userLocale +
                            "/customize/import" + customizeParam,
                    "text": js.core.translate(
                                "admin.menu.changeLayout.importExport",
                                js.core.userLocale
                            )
                } ) ) ); */

                tabs.css( {
                        "width": "845px",
                        "height": "450px"
                    } )
                    .tabs( {
                        "cache": true,
                        "spinner": js.core.translate(
                            "default.loading", js.core.userLocale
                        ),
                        "ajaxOptions": {
                            "error": function( xhr, status, index, anchor ) {
                                js.console.warn( this, index, status, xhr );
                                $( anchor.hash ).html( js.core.translate(
                                    "default.error", js.core.userLocale
                                ) );
                            }
                        },
                        "beforeLoad": function ( event, ui ) {
                            ui.panel.html( "" )
                                    .append(
                                        $( "<p>" ).css( {
                                                       "margin": "0px",
                                                       "padding": "50px",
                                                       "text-align": "center"
                                                   } )
                                                  .text( js.core.translate( "default.loading" ) )
                                    );
                        },
                        "load": function ( event, ui ) {
                            var panel = $( ui.panel ),
                                form  = panel.find( "form" );

                            form.attr( "data-js-type", form.attr( "data-js-type" ) + " js.form.cancel" )
                                .data( "jsCancelButtons", {
                                    "default.cancel": "javascript:js.admin.menu.close();"
                                } );

                            js.core.parseDocument( panel );
                        }
                    } );

                close = js.core.layer( tabs );
                return false;
            } );

        menu.find( "> li > .new-paragraph" )
            .click( function () {
                var root = ( ! content || ! content.length || editLay )
                             ? layout : content;

                if ( root && root.length )
                {
                    js.require( "js.paragraph", function () {
                        js.paragraph.append( root );
                    } );
                }
                else
                {
                    js.require( "js.ui.dialog", function () {
                        js.ui.dialog.alert( {
                            "title" : js.core.translate(
                                "default.error",
                                js.core.userLocale
                            ),
                            "message" : js.core.translate(
                                "admin.error.noContent",
                                js.core.userLocale
                            )
                        } );
                    } );
                }

                return false;
            } );

        menu.find( "> li > .new-content" )
            .click( function () {
                var tabh  = $( "<ul />" ),
                    tabs  = $( "<div />" ).append( tabh ),
                    close;

                global.Zork.Admin.prototype.menu.close = function () {
                    close();
                    global.Zork.Admin.prototype.menu.close = function () {};
                };

                tabh.append( $( "<li />").append( $( "<a />", {
                    "href": "/app/" + js.core.userLocale + "/paragraph/import-content" +
                            "?adminLocale=" + js.core.defaultLocale,
                    "text": js.core.translate(
                                "admin.menu.importContent.predefined",
                                js.core.userLocale
                            )
                } ) ) );

                tabs.css( {
                        "width": "600px",
                        "height": "400px"
                    } )
                    .tabs( {
                        "cache": true,
                        "spinner": js.core.translate(
                            "default.loading", js.core.userLocale
                        ),
                        "ajaxOptions": {
                            "error": function( xhr, status, index, anchor ) {
                                js.console.warn( this, index, status, xhr );
                                $( anchor.hash ).html( js.core.translate(
                                    "default.error", js.core.userLocale
                                ) );
                            }
                        },
                        "beforeLoad": function ( event, ui ) {
                            ui.panel.html( "" )
                                    .append(
                                        $( "<p>" ).css( {
                                                       "margin": "0px",
                                                       "padding": "50px",
                                                       "text-align": "center"
                                                   } )
                                                  .text( js.core.translate( "default.loading" ) )
                                    );
                        },
                        "load": function ( event, ui ) {
                            var panel = $( ui.panel ),
                                form  = panel.find( "form" );

                            form.attr( "data-js-type", form.attr( "data-js-type" ) + " js.form.cancel" )
                                .data( "jsCancelButtons", {
                                    "default.cancel": "javascript:js.admin.menu.close();"
                                } );

                            js.core.parseDocument( panel );
                        }
                    } );

                close = js.core.layer( tabs );
                return false;
            } );

        menu.find( "> li > a[href!=#]" )
            .click( function () {
                global.location.href = this.href;
            } );

        try
        {
            element.css( "position", "fixed" );
        }
        catch ( e )
        {
            element.css( "position", "absolute" );
        }

        var originalPosition = position;

        element.draggable( {
            "axis"          : "x",
            "snap"          : "body",
            "snapMode"      : "inner",
            "snapTolerance" : 100,
            "containment"   : "body",
            "revert"        : false,
            "handle"        : ".js-adminmenu-header .text",
            "start"     : function () {
                originalPosition = position;
                $( this ).css( "right", "auto" );
            },
            "stop"          : function () {
                var self = $( this ),
                    to   = {},
                    opp  = corners[position];

                if ( originalPosition == position )
                {
                    to[position] = offset( self, position );
                    to[opp] = "";

                    self.css( to );

                    to[position] = 0;
                    delete to[opp];

                    self.animate( to, 1000, "easeOutQuart", function () {
                        self.css( {
                            "left": "",
                            "right": ""
                        } );
                    } );
                }
                else
                {
                    to[position] = offset( self, position );
                    to[originalPosition] = "auto";

                    self.css( to );
                    to[position] = 0;
                    delete to[originalPosition];

                    posRpc( originalPosition = position );

                    self.animate( to, 1000, "easeOutQuart", function () {
                        self.css( {
                            "left": "",
                            "right": ""
                        } );
                    } );
                }
            },
            "drag"          : function () {
                var self = $( this ),
                    half = $( global.document ).width() / 2,
                    curr = self.offset().left + ( self.width() / 2 );

                if ( curr > half )
                {
                    if ( position != "right" )
                    {
                        changePos( "right" );
                    }
                }
                else
                {
                    if ( position != "left" )
                    {
                        changePos( "left" );
                    }
                }
            }
        } );
    };

    global.Zork.Admin.prototype.menu.isElementConstructor = true;

    global.Zork.Admin.prototype.menu.refresh = function ()
    {
        layout  = papr( "editLayout" );
        content = papr( "editContent" );

        $( menus ).find( "li > .edit-content" )
                      .toggleClass( "ui-state-default", !! content.length )
                      .find( ".status" )
                          .css( "display", content.length ? "" : "none" )
                      .end()
                      .parent( "li" )
                          .toggleClass( "disabled", ! content.length )
                      .end()
                  .end()
                  .find( "li > .edit-content-paragraph" )
                      .parent( "li" )
                          .toggleClass( "disabled", ! content.length )
                      .end()
                  .end()
                  .find( "li > .edit-layout" )
                      .toggleClass( "ui-state-default", !! layout.length )
                      .parent( "li" )
                          .toggleClass( "disabled", ! layout.length )
                      .end()
                  .end();
    };

    global.Zork.Admin.prototype.menu.close = function () {};

    /**
     * Locale form/element element
     *
     * @memberOf Zork.Admin
     */
    global.Zork.Admin.prototype.locale = function ( element )
    {
        element = $( element );

        element.find( ":input" )
               .change( function () {
                   element.submit();
               } );
    };

    global.Zork.Admin.prototype.locale.isElementConstructor = true;

    /**
     * Locale form/element element
     *
     * @memberOf Zork.Admin
     */
    global.Zork.Admin.prototype.updatePackages = function ( element )
    {
        element = $( element );

        var messageContainer = element.find( ".messages" ).text( "" ),
            outputContainer  = element.find( ".output" ).text( "" ),
            ajaxError        = js.core.translate( "admin.packages.update.ajaxError" ),
            checkTick        = parseInt( element.data( "jsUpdatepackagesChecktick" ), 10 ) || 1000,
            checkTimeout     = null,
            sendMessage      = function ( message, msgclass ) {
                messageContainer.append(
                    $( "<p>" ).text( message )
                              .addClass( msgclass || "info" )
                );
            },
            goHome = function () {
                global.location.href = "/app/"
                    + ( js.core.defaultLocale || "en" )
                    + "/admin/package/list";
            },
            check = function () {
                checkTimeout = null;

                $.ajax( {
                    "url": "/app/maintenance.php?update=status",
                    "async": true,
                    "cache": false,
                    "dataType": "json",
                    "success": function ( data ) {
                        outputContainer.text( data.output || "" );
                        messageContainer.text( "" );

                        if ( data.messages )
                        {
                            var t;

                            for ( var i in data.messages )
                            {
                                t = String( js.core.translate( i ) );

                                if ( data.messages[i] &&
                                     Array.isArray( data.messages[i] ) &&
                                     data.messages[i].length )
                                {
                                    t = t.format.apply( t, data.messages[i] );
                                }

                                sendMessage( t );
                            }
                        }

                        if ( ( "result" in data ) && null !== data.result )
                        {
                            checkTimeout = setTimeout( goHome, checkTick );
                        }
                    },
                    "error": function ( xhr ) {
                        if ( 404 == xhr.status )
                        {
                            checkTimeout = setTimeout( goHome, checkTick );
                        }
                    },
                    "complete": function () {
                        if ( ! checkTimeout )
                        {
                            checkTimeout = setTimeout( check, checkTick );
                        }
                    }
                } );
            };

        $.ajax( {
            "url": "/app/" + ( js.core.defaultLocale || "en" ) + "/admin/package/update/run",
            "async": true,
            "cache": false,
            "dataType": "json",
            "success": function ( data ) {
                if ( data.started )
                {
                    sendMessage( ajaxError );
                    js.console.error( data );
                }
                else
                {
                    checkTimeout = setTimeout( check, checkTick );
                }
            },
            "error": function ( xhr, options, err ) {
                sendMessage( ajaxError );
                js.console.error( err );
            }
        } );
    };

    global.Zork.Admin.prototype.updatePackages.isElementConstructor = true;

} ( window, jQuery, zork ) );
