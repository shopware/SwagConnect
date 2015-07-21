//{namespace name=backend/bepado/view/main}

//{block name='backend/bepado/view/config/units/mapping'}
Ext.define('Shopware.apps.Bepado.view.config.units.Mapping', {
    extend: 'Ext.container.Container',
    alias: 'widget.bepado-units-mapping',

    layout: 'fit',

    snippets: {
        unitHeader: Ext.String.format('{s name=config/units/bepado_unit_header}[0] Einheit{/s}', marketplaceName)
    },

    initComponent: function() {
        var me = this;
        me.unitsStore = Ext.create('Shopware.apps.Bepado.store.config.Units').load();
        me.bepadoUnitsStore = Ext.create('Shopware.apps.Bepado.store.config.BepadoUnits').load();



        Ext.applyIf(me, {
            items: [
                Ext.create('Ext.grid.Panel', {
                    alias: 'widget.bepado-units-mapping-list',
                    store: me.unitsStore,
                    selModel: 'cellmodel',
                    plugins: [ me.createCellEditor() ],
                    columns: [{
                        header: '{s name=config/units/shopware_unit_header}Shopware unit{/s}',
                        dataIndex: 'shopwareUnitName',
                        flex: 1
                    }, {
                        header: me.snippets.unitHeader,
                        dataIndex: 'bepadoUnit',
                        flex: 1,
                        editor: {
                            xtype: 'combo',
                            store: me.bepadoUnitsStore,
                            displayField: 'name',
                            valueField: 'key'
                        },
                        renderer: function (value) {
                            var index = me.bepadoUnitsStore.findExact('key', value);
                            if (index > -1) {
                                return me.bepadoUnitsStore.getAt(index).get('name');
                            }

                            return value;
                        }
                    }]
                })
            ]
        });

        me.callParent(arguments);
    },

    createCellEditor: function() {
        var me = this;

        me.cellEditor = Ext.create('Ext.grid.plugin.CellEditing', {
            clicksToMoveEditor: 1,
            autoCancel: true
        });

        return me.cellEditor;
    }
});
//{/block}