/**
 * Paragraph dashboard
 * @package zork
 * @subpackage paragraph
 * @author David Pozsar <david.pozsar@megaweb.hu>
 */
( function ( global, $, js )
{
    "use strict";

    if ( typeof js.paragraph.dashboard.boxes !== "undefined" )
    {
        return;
    }

    /**
     * @class Boxes dashborad
     * @memberOf global.Zork.Paragraph.prototype.dashboard
     */
    global.Zork.Paragraph.prototype.dashboard.boxes = function ( form, element )
    {
        form    = $( form );
        element = $( element );

        return {
            "update": function () {
            },
            "restore": function () {
            }
        };
    };

} ( window, jQuery, zork ) );
