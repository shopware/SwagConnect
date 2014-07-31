/**
 * Shopware 4
 * Copyright © shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */
/**
 * Shopware SwagBepado Plugin
 *
 * @category Shopware
 * @package Shopware\Plugins\SwagBepado
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
//{namespace name=backend/bepado/view/main}
//{block name="backend/bepado/view/shipping_groups/add_group"}
Ext.define('Shopware.apps.Bepado.view.config.shippingGroups.AddGroup', {
    extend: 'Ext.window.Window',
    alias: 'widget.bepado-shipping-add-group',

    layout: 'fit',
    width: 500,
    height:'30%',
    modal: true,
    title: '{s name=config/shipping_groups/add_group}Add group{/s}',

    initComponent: function() {
        var me = this;

        Ext.applyIf(me, {
            items: [ me.getForm(),
            me.getButtons()]
        });

        me.callParent(arguments);
    },

    /**
     * Returns generated shipping group form
     */
    getForm: function() {
        var me = this;

        return {
            xtype: 'form',
            url: '{url controller=ShippingGroups action=createShippingGroup}',
            layout: 'anchor',
            bodyPadding: 10,
            defaults: {
                anchor: '100%'
            },
            items: [
                {
                    xtype: 'textfield',
                    name: 'groupName',
                    fieldLabel: '{s name=config/shipping_groups/group_name}Group name{/s}',
                    allowBlank: false
                }
            ]
            ,
            buttons: [ me.getButtons() ]
        };
    },

    /**
     * Creates save bottom buttons
     * @returns string
     */
    getButtons: function() {
        return {
            text: '{s name=config/shipping_groups/save}Save{/s}',
            cls: 'primary',
            formBind: true,
            disabled: true,
            action: 'save'
        };
    }
});
//{/block}