//{namespace name=backend/connect/view/main}

//{block name="backend/connect/view/import/panel"}
Ext.define('Shopware.apps.Connect.view.import.Panel', {
    extend: 'Ext.container.Container',
    alias: 'widget.connect-import',

    border: false,
    layout: 'vbox',
    padding: '10px',
    width: '100%',
    autoScroll: true,

    snippets: {
        connectProductsTitle: '{s name=import/shopware_connect_products}shopware Connect Produkte{/s}',
        showOnlyConnectProductsLabel: '{s name=import/show_only_connect_products}Nur shopware Connect Produkte anzeigen{/s}',
        activateProductsLabel: '{s name=import/activate_products}Produkte aktivieren{/s}',
        myProductsTitle: '{s name=import/my_products}Meine Produkte{/s}'
    },

    initComponent: function() {
        var me = this;

        Ext.applyIf(me, {
            items: [
                {
                    xtype: 'container',
                    layout: 'hbox',
                    width: '100%',
                    items: [
                        {
                            xtype: 'container',
                            html: '<h1 class="shopware-connect-color" style="font-size: large">' + me.snippets.connectProductsTitle  + '</h1>',
                            width: 400,
                            height: 30
                        },
                        {
                            xtype: 'container',
                            html: '<h1 style="font-size: large">' + me.snippets.myProductsTitle + '</h1>',
                            margin: '0 0 0 60px',
                            width: 400
                        }
                    ]
                },
                {
                    xtype: 'container',
                    layout: 'hbox',
                    width: '100%',
                    items: [
                        {
                            xtype: 'connect-remote-categories'
                        },
                        {
                            xtype: 'button',
                            alias: 'widget.arrow-import-categories',
                            cls: 'import-arrow',
                            action: 'importRemoteCategory',
                            border: false,
                            padding: '2px',
                            margin: '125px 0 0 0',
                            width: 50,
                            height: 50
                        },
                        {
                            xtype: 'connect-own-categories'
                        }
                    ]
                }, {
                    xtype: 'container',
                    layout: 'hbox',
                    items: [
                        Ext.create('Shopware.apps.Connect.view.import.RemoteProducts', {
                            width: 400,
                            height: 300,
                            margin: '10px 0 0 0'
                        }),
                        {
                            xtype: 'container',
                            items: [
                                Ext.create('Shopware.apps.Connect.view.import.LocalProducts', {
                                    width: 400,
                                    height: 300,
                                    margin: '10px 0 0 50px'
                                }),
                                {
                                    xtype : 'checkbox',
                                    name : 'attribute[connectAllowed]',
                                    action: 'show-only-connect-products',
                                    margin: '15px 0 0 50px',
                                    checked: true,
                                    boxLabel : me.snippets.showOnlyConnectProductsLabel
                                },{
                                    xtype: 'button',
                                    cls: 'primary sc-btn-right',
                                    action: 'activateProducts',
                                    text: me.snippets.activateProductsLabel
                                }
                            ]
                        }

                    ]
                }
        ]
        });

        me.callParent(arguments);
    }
});
//{/block}