//{namespace name=backend/bepado/view/main}

//{block name="backend/bepado/view/import/list"}
Ext.define('Shopware.apps.Bepado.view.import.List', {
    extend: 'Ext.grid.Panel',
    alias: 'widget.bepado-import-list',

    border: false,

    store: 'import.List',

    selModel: {
        selType: 'checkboxmodel',
        mode: 'MULTI'
    },

    snippets: {
        description: Ext.String.format('{s name=import/products/description}Der Produktimport erfolgt automatisch, sobald Sie Produkte anderer Händler auf [0] abonnieren und eine Verbindung zu [0] hergestellt worden ist. Sie können die Produkte in diesem Menü nach dem Import aktivieren, deaktivieren oder weiter bearbeiten.{/s}', marketplaceName)
    },

    initComponent: function() {
        var me = this;

        Ext.applyIf(me, {
            dockedItems: [
                me.getToolbar(),
                me.getDescriptionBar(),
                me.getPagingToolbar()
            ],
            columns: me.getColumns()
        });

        me.groupingFeature = me.createGroupingFeature();
        me.features =  [ me.groupingFeature ];

        me.callParent(arguments);

        me.store.load();
    },

    getDescriptionBar: function() {
        var me =this;

        return {
            xtype: 'container',
            padding: '10 0 10 10',
            html: me.snippets.description
        };
    },

    createGroupingFeature: function() {
        var me = this;

        return Ext.create('Ext.grid.feature.Grouping', {
            groupHeaderTpl: Ext.create('Ext.XTemplate',
                '<span>{ name:this.formatHeader }</span>',
                '<span>&nbsp;({ rows.length } Products)</span>',
                {
                    formatHeader: function(category) {
                        return category;
                    }
                }
            )
        });
    },

    getColumns: function() {
        var me = this;
        return [{
            header: '{s name=export/columns/number}Number{/s}',
            dataIndex: 'number',
            flex: 2
        }, {
            header: '{s name=export/columns/name}Name{/s}',
            dataIndex: 'name',
            flex: 4
        }, {
            header: '{s name=export/columns/supplier}Supplier{/s}',
            dataIndex: 'supplier',
            flex: 3
        }, {
            header: '{s name=export/columns/status}Status{/s}',
            xtype: 'booleancolumn',
            dataIndex: 'active',
            sortable: false,
            renderer: function(value, metaData, record) {
                if(record.get('bepadoStatus')) {
                    return record.get('bepadoStatus');
                }
                return value ? 'Aktiv' : 'Inaktiv';
            },
            width: 50
        }, {
            header: '{s name=export/columns/price}Price{/s}',
            xtype: 'numbercolumn',
            dataIndex: 'price',
            align: 'right',
            width: 55
        }, {
            header: '{s name=export/columns/tax}Tax{/s}',
            xtype: 'numbercolumn',
            dataIndex: 'tax',
            align: 'right',
            flex: 1
        }, {
            header: '{s name=export/columns/stock}Stock{/s}',
            xtype: 'numbercolumn',
            dataIndex: 'inStock',
            format: '0,000',
            align: 'right',
            flex: 1
        }, {
            xtype: 'actioncolumn',
            width: 26,
            items: [{
                action: 'edit',
                cls: 'editBtn',
                iconCls: 'sprite-pencil',
                handler: function(view, rowIndex, colIndex, item, opts, record) {
                    Shopware.app.Application.addSubApplication({
                        name: 'Shopware.apps.Article',
                        action: 'detail',
                        params: {
                            articleId: record.get('id')
                        }
                    });
                }
            }]
        }];
    },


    /**
     * Creates a paging toolbar with additional page size selector
     *
     * @returns Array
     */
    getPagingToolbar: function() {
        var me = this;
        var pageSize = Ext.create('Ext.form.field.ComboBox', {
            labelWidth: 120,
            cls: Ext.baseCSSPrefix + 'page-size',
            queryMode: 'local',
            width: 180,
            listeners: {
                scope: me,
                select: function(combo, records) {
                    var record = records[0],
                        me = this;

                    me.store.pageSize = record.get('value');
                    me.store.loadPage(1);
                }
            },
            store: Ext.create('Ext.data.Store', {
                fields: [ 'value' ],
                data: [
                    { value: '10' },
                    { value: '20' },
                    { value: '40' },
                    { value: '60' },
                    { value: '80' },
                    { value: '100' },
                    { value: '250' },
                    { value: '500' },
                ]
            }),
            displayField: 'value',
            valueField: 'value',
            editable: false,
            emptyText: '20'
        });
        pageSize.setValue(me.store.pageSize);

        var pagingBar = Ext.create('Ext.toolbar.Paging', {
            store: me.store,
            dock:'bottom',
            displayInfo:true
        });

        pagingBar.insert(pagingBar.items.length - 2, [ { xtype: 'tbspacer', width: 6 }, pageSize ]);
        return pagingBar;
    },

    getToolbar: function() {
        var me = this;
        return {
            xtype: 'toolbar',
            enableOverflow: true,
            ui: 'shopware-ui',
            dock: 'top',
            border: false,
            items: me.getTopBar()
        };
    },

    getTopBar:function () {
        var me = this;
        var items = [];
        items.push({
            iconCls:'sprite-plus-circle-frame',
            text:'{s name=export/options/activate_text}Enable products{/s}',
            action:'activate'
        });
        items.push({
            iconCls:'sprite-minus-circle-frame',
            text:'{s name=export/options/disable_text}Disable products{/s}',
            action:'deactivate'
        });
        items.push({
            iconCls:'sprite-blue-folders-stack',
            text:'{s name=import/options/assign_category_text}Assign category{/s}',
            action:'assignCategory'
        });
        items.push({
            xtype: 'tbseparator'
        });
        items.push({
            xtype: 'checkbox',
            labelWidth: 180,
            fieldLabel: '{s name=import/toolbar/show_in_groups}Show in groups{/s}',
            checked: true,
            listeners: {
                'change': function(checkbox) {
                    if (checkbox.getValue()) {
                        me.groupingFeature.enable();
                    } else {
                        me.groupingFeature.disable();
                    }
                }
            }
        });
        items.push('->');
        items.push({
            iconCls:'sprite-minus-circle-frame',
            text:'{s name=export/options/unsubscribe_delete_text}Unsubscribe and delete products{/s}',
            action:'unsubscribe',
            // do not remove listeners
            // this button doesn't work with
            // action when enableOverflow is true
            listeners: {
                'click': function() {
                    me.fireEvent('unsubscribeAndDelete', this);
                }
            }
        });
        return items;
    }
});
//{/block}