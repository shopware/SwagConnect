//{namespace name=backend/connect/view/main}

/**
 * Shopware Controller - Cache backend module
 */
//{block name="backend/connect/controller/main"}
Ext.define('Shopware.apps.Connect.controller.Main', {

    extend: 'Enlight.app.Controller',

    stores: [
        'main.Navigation',
        'export.List', 'import.RemoteCategories', 'import.RemoteProducts', 'import.LocalProducts',
        'changed_products.List',
        'log.List',
		'config.General', 'config.Import', 'config.Export', 'config.CustomerGroup', 'config.PriceGroup',
        'config.Units', 'config.ConnectUnits', 'config.MarketplaceAttributes', 'config.LocalProductAttributes'
    ],
    models: [
        'main.Mapping', 'main.Product',
        'export.List', 'import.List',
        'changed_products.List', 'changed_products.Product', 'log.List',
        'config.General', 'config.Import', 'config.Units', 'config.MarketplaceAttributes',
        'config.ConnectUnit', 'config.Pages', 'config.LocalProductAttributes', 'config.PriceGroup'
    ],

    refs: [
        { ref: 'window', selector: 'connect-window' },
        { ref: 'navigation', selector: 'connect-navigation' },
        { ref: 'panel', selector: 'connect-panel' },
        { ref: 'exportList', selector: 'connect-export-list' },
        { ref: 'exportFilter', selector: 'connect-export-filter' },
        { ref: 'importList', selector: 'connect-import-list' },
        { ref: 'changeView', selector: 'connect-changed-products-tabs' },
        { ref: 'changedList', selector: 'connect-changed-products-list' },
        { ref: 'logList', selector: 'connect-log-list' },
        { ref: 'logFilter', selector: 'connect-log-filter' },
        { ref: 'logTabs', selector: 'connect-log-tabs' },
        { ref: 'marketeplaceMappingPanel', selector: 'connect-config-marketplace-attributes' },
        { ref: 'marketeplaceMapping', selector: 'connect-marketplace-attributes-mapping' },
        { ref: 'exportConfigForm', selector: 'connect-config-export-form' },
        { ref: 'unitsMapping', selector: 'connect-units-mapping-list' }
    ],

    messages: {
        saveMappingTitle: '{s name=mapping/message/title}Save category mapping{/s}',
        saveMappingSuccess: '{s name=mapping/message/success}Category mapping has been saved.{/s}',
        saveMappingError: '{s name=mapping/message/error}Category mapping could not be saved.{/s}',


        insertOrUpdateProductTitle: '{s name=export/message/import_product_title}Products export{/s}',
        insertOrUpdateProductMessage: '{s name=export/message/import_product_messag}Products were marked for inserting / updating.{/s}',
        deleteProductTitle: '{s name=export/message/delete_title}Products export{/s}',
        deleteProductMessage: '{s name=export/message/delete_message}Products were marked for deleting.{/s}',

        activateProductTitle: '{s name=import/message/activate_title}Products import{/s}',
        activateProductMessage: '{s name=import/message/activate_message}Products have been activated.{/s}',
        disableProductTitle: '{s name=import/message/disable_title}Products import{/s}',
        disableProductMessage: '{s name=import/message/disable_message}Products have been disabled.{/s}',
        unsubscribeProductTitle: '{s name=import/message/unsubscribe_title}Products unsubscribed{/s}',
        unsubscribeProductMessage: '{s name=import/message/unsubscribe_message}Products have been unsubscribed.{/s}',


        applyMappingToChildCategoriesTitle: '{s name=mapping/applyConfirmTitle}Apply to child categories?{/s}',
        applyMappingToChildCategoriesMessage: '{s name=mapping/applyConfirmMessage}Do you want to apply this mapping to all empty child categories? This will immediately save the current mapping, all other unsaved changes will be lost{/s}',

        updatePartOneMessage: Ext.String.format('{s name=config/message/update_part_one}Update to [0] will take{/s}', marketplaceName),
        updatePartTwoMessage: '{s name=config/message/update_part_two}to finish{/s}',
        doneMessage: '{s name=config/message/done}Done{/s}',

        hours: '{s name=hours}Hour(s){/s}',
        minutes: '{s name=minutes}Minute(s){/s}',
        seconds: '{s name=seconds}Second(s){/s}',

        adoptUnitsTitle: '{s name=config/import/adopt_units_confirm_title}Maßeinheiten übernehmen{/s}',
        adoptUnitsMessage: '{s name=config/import/adopt_units_confirm_message}Möchten Sie die importieren Maßeinheiten in Ihren Shop übernehmen?{/s}',

        priceFieldIsNotSupported: '{s name=config/export/priceFieldIsNotSupported}Preisfeld ist nicht gepflegt{/s}',

        importConnectCategoriesTitle: '{s name=mapping/importConnectCategoriesTitle}Import categories?{/s}',
        importConnectCategoriesMessage: '{s name=mapping/importConnectCategoriesMessage}Do you want to import all subcategories of »[0]« to you category »[1]«?{/s}',
        importAssignCategoryConfirm: '{s name=import/message/confirm_assign_category}Assign the selected »[0]« products to the category selected below.{/s}'
    },

    /**
     * Class property which holds the main application if it is created
     *
     * @default null
     * @object
     */
    mainWindow: null,

    /**
     * Init component. Basically will create the app window and register to events
     */
    init: function () {
        var me = this;

        me.mainWindow = me.getView('main.Window').create({
            'action': me.subApplication.action
        }).show();

        me.control({
            'connect-navigation': {
                select: me.onSelectNavigationEntry
            },
            'connect-config button[action=save-general-config]': {
                click: me.onSaveConfigForm
            },
            'connect-config-form': {
                calculateFinishTime: me.onCalculateFinishTime
            },
			'connect-config-import-form button[action=save-import-config]': {
                click: me.onSaveImportConfigForm
            },
            'connect-config-import-form checkbox[name=hideAssignedUnits]': {
                change: me.onHideAssignedUnits
            },
            'connect-config-import-form button[action=adoptUnits]': {
                click: me.onAdoptUnits
            },
			'connect-config-export-form button[action=save-export-config]': {
                click: me.onSaveExportConfigForm
            },
            'connect-config-export-form combobox[name=priceGroupForPriceExport]': {
                change: me.onChangePriceGroupForPrice
            },
            'connect-config-export-form combobox[name=priceGroupForPurchasePriceExport]': {
                change: me.onChangePriceGroupForPurchasePrice
            },
            'connect-config-export-form combobox[name=priceFieldForPriceExport]': {
                change: me.onChangePriceFieldForPrice
            },
            'connect-config-export-form combobox[name=priceFieldForPurchasePriceExport]': {
                change: me.onChangePriceFieldForPurchasePrice
            },
			'connect-mapping button[action=save]': {
                click: me.onSaveMapping
            },
            'connect-export button[action=add]': {
               click: me.onExportFilterAction
            },
            'connect-export button[action=delete]': {
                click: me.onExportFilterAction
            },
            'connect-export-filter button[action=category-clear-filter]': {
                click: me.onExportCategoryFilterClearAction
            },
            'connect-export-filter textfield[name=searchfield]': {
                change: function(field, value) {
                    var table = me.getExportList(),
                        store = table.getStore();
                        store.filters.removeAtKey('search');
                    if (value.length > 0 ) {
                        store.filters.add('search', new Ext.util.Filter({
                            property: 'search',
                            value: '%' + value + '%'
                        }));
                    }
                    store.load();
                }
            },

            'connect-export-filter base-element-select[name=supplierId]': {
                change: function(field, value) {
                    var table = me.getExportList(),
                        store = table.getStore();

                        store.filters.removeAtKey('supplierId');
                    if (value) {
                        store.filters.add('supplierId', new Ext.util.Filter({
                            property: field.name,
                            value: value
                        }));
                    }
                    store.load();
                }
            },
            'connect-export-filter [name=exportStatus]': {
                change: function(field, value) {
                    var table = me.getExportList(),
                        store = table.getStore();
                    if(!value) {
                        return;
                    }
                    store.filters.removeAtKey('exportStatus');
                    if(field.inputValue != '') {
                        store.filters.add('exportStatus', new Ext.util.Filter({
                            property: field.name,
                            value: field.inputValue
                        }));
                    }
                    store.load();
                }
            },
            'connect-export-filter treepanel': {
                select: function(tree, node) {
                    var table = me.getExportList(),
                        store = table.getStore();

                    if (!node) {
                        store.filters.removeAtKey('exportCategoryFilter');
                    } else {
                        store.filters.removeAtKey('exportCategoryFilter');
                        store.filters.add('exportCategoryFilter', new Ext.util.Filter({
                            property: 'categoryId',
                            value:  node.get('id')
                        }));
                    }
                    store.load();
                }
            },
            'connect-changed-products-list': {
                'selectionchange': me.onChangedProductsSelectionChanged
            },
            'connect-log-filter [filter=commandFilter]': {
                change: function(field, value) {
                    var table = me.getLogList(),
                        store = table.getStore();

                    store.getProxy().extraParams['commandFilter_' + field.name] = value;
                    store.reload();
                }
            },
            'connect-log-filter [name=error]': {
                change: function(field, value) {
                    var table = me.getLogList(),
                        store = table.getStore();

                    if (!value) {
                        return;
                    }

                    store.getProxy().extraParams.errorFilter = field.inputValue;
                    store.reload();
                }
            },
            'connect-log-filter textfield[name=searchfield]': {
                change: function(field, value) {
                    var table = me.getLogList(),
                        store = table.getStore();

                    if (value.length === 0 ) {
                        store.clearFilter();
                    } else {
                        store.filters.clear();
                        store.filter(
                            'search',
                            '%' + value + '%'
                        );
                    }
                }
            },
            'connect-log-list': {
                'selectionchange': function(grid, selected, eOpts) {
                    var me = this,
                        record,
                        tabs = me.getLogTabs(),
                        request = tabs.down('textarea[name=request]'),
                        response = tabs.down('textarea[name=response]');

                    // make sure that we have a selection
                    if (selected && selected.length > 0) {
                        record = selected[0];

                        request.setValue(record.get('request'));
                        response.setValue(record.get('response'));
                    }

                }
            },
            'connect-log-list button[action=clear]': {
                click: function() {
                    var table = me.getLogList(),
                        store = table.getStore();

                    Ext.MessageBox.confirm(
                        '{s name=log/clear/confirm}Delete log?{/s}',
                        '{s name=log/clear/message}You are about to delete all log entries. Continue?{/s}',
                        function (response) {
                            if ( response !== 'yes' ) {
                                return;
                            }
                            Ext.Ajax.request({
                                url: '{url action=clearLog}',
                                method: 'POST',
                                success: function(response, opts) {
                                    store.reload();
                                }
                            });

                        }
                    );
                }
            },
            'connect-log-list checkbox[name=logRequest]': {
                change: me.onChangeLogging
            },
            'connect-marketplace-attributes-mapping button[action=save]': {
                click: function () {
                    me.saveMarketplaceAttributesMapping();
                }
            }
        });

        if (me.subApplication.action == 'Settings') {
            me.populateLogCommandFilter();
        }

        me.callParent(arguments);
    },

    /**
     * Dynamically create filter fields for all known command types
     */
    populateLogCommandFilter: function() {
        var me = this,
            logList = me.getLogList(),
            store = logList.store,
            container = me.getLogFilter().down('fieldcontainer');

        Ext.Ajax.request({
            url: '{url action=getLogCommands}',
            method: 'POST',
            success: function(response, opts) {
                var data;

                if (!response || !response.responseText) {
                    return;
                }

                data = Ext.JSON.decode(response.responseText);

                Ext.each(data.data, function(command) {
                    store.getProxy().extraParams['commandFilter_' + command] = true;
                    container.add({
                        boxLabel  : command,
                        name      : command,
                        inputValue:  true,
                        checked   :  true,
                        filter    : 'commandFilter'
                    });
                });

                store.reload();
            },
            failure: function(response, opts) {
                Shopware.Notification.createGrowlMessage('{s name=error}Error{/s}', response.responseText);
            }

        });

    },

    /**
     * Method called when clear category button is clicked.
     */
    onExportCategoryFilterClearAction: function() {
        var me = this;

        var table = me.getExportList(),
            filter = me.getExportFilter(),
            store = table.getStore();
        store.filters.removeAtKey('exportCategoryFilter');
        store.load();

        //deselect all nodes
        Ext.getCmp('export-category-filter').getSelectionModel().deselectAll();
    },

    /**
     * Callback method called when the user selects a product in the "changed products" view.
     * Will populate the bottom "change view" grid with the correct tabs
     *
     * @param grid
     * @param selected
     * @param eOpts
     */
    onChangedProductsSelectionChanged: function(grid, selected, eOpts) {
        var me = this,
            record,
            remoteChangeSet,
            changeRecord,
            changeFlag = 0, flags,
            changeView = me.getChangeView();

        // make sure that we have a selection
        if (selected && selected.length > 0) {
            record = selected[0];

            // Decode the lastUpdate info
            remoteChangeSet = Ext.JSON.decode(record.get('lastUpdate'));

            // Build a record for the changeset
            changeRecord = Ext.create('Shopware.apps.Connect.model.changed_products.Product', {
                shortDescriptionLocal: record.get('description'),
                shortDescriptionRemote: remoteChangeSet['shortDescription'],

                longDescriptionLocal: record.get('descriptionLong'),
                longDescriptionRemote: remoteChangeSet['longDescription'],

                nameLocal: record.get('name'),
                nameRemote: remoteChangeSet['name'],

                priceLocal: record.get('price'),
                priceRemote: remoteChangeSet['price'],

                imageLocal: record.get('images'),
                imageRemote: remoteChangeSet['image'].join('|')
            });

            // Read updateFlag and build update flag object
            changeFlag = record.get('lastUpdateFlag');
            flags = {
                2: 'shortDescription',
                4: 'longDescription',
                8: 'name',
                16: 'image',
                32: 'price',
                64: 'imageInitialImport'
            };

            // Check all flags and show the corresponding tab if it is active
            // if not, remove the tab without destroying the component
            Ext.each(Object.keys(flags), function(key) {
                var fieldName = flags[key],
                    container = changeView.fields[fieldName];

                if (container) {
                    changeView.remove(container, false);
                }

                if (changeFlag & key && container) {
                    changeView.add(container);

                    container.applyButton.handler = function() {
                        me.applyChanges(fieldName, changeRecord.get(fieldName + 'Remote'), record.get('id'));
                    }

                    container.loadRecord(changeRecord);
                }
            });

            changeView.setTitle(record.get('name'));

            // hotfix: make sure that the tab is displayed correctly
            changeView.setActiveTab(0);
            changeView.setActiveTab(1);
            changeView.setActiveTab(0);

        }
    },

    /**
     * Callback to apply a given change for a given product
     *
     * @param type
     * @param value
     * @param articleId
     */
    applyChanges: function(type, value, articleId) {
        var me = this,
            changedProductsList = me.getChangedList(),
            store = changedProductsList.store;

        Ext.Ajax.request({
            url: '{url action=applyChanges}',
            method: 'POST',
            params: {
                type: type,
                value: value,
                articleId: articleId
            },
            success: function(response, opts) {
                me.createGrowlMessage('{s name=success}Success{/s}', '{s name=changed_products/success/message}Successfully applied changes{/s}');
                store.reload();
            },
            failure: function(response, opts) {
                me.createGrowlMessage('{s name=error}Error{/s}', response.responseText);
            }

        });

    },

    /**
     * Callback function that will insert or delete a product from/for export
     *
     * @param btn
     */
    onExportFilterAction: function(btn) {
        var me = this,
            list = me.getExportList(),
            records = list.selModel.getSelection(),
            ids = [],
            url,
            message,
            messages = [],
            title;

        if(btn.action == 'add') {
            url = '{url action=insertOrUpdateProduct}';
            title = me.messages.insertOrUpdateProductTitle;
            message = me.messages.insertOrUpdateProductMessage;
        } else if(btn.action == 'delete') {
            url = '{url action=deleteProduct}';
            title = me.messages.deleteProductTitle;
            message = me.messages.deleteProductMessage;
        } else {
            return;
        }

        Ext.each(records, function(record) {
            ids.push(record.get('id'));
        });

        list.setLoading();
        
        Ext.Ajax.request({
            url: url,
            method: 'POST',
            params: {
                'ids[]': ids
            },
            success: function(response, opts) {
                var sticky = false;
                if (response.responseText) {
                    var operation = Ext.decode(response.responseText);
                    if (operation) {
                        if (!operation.success && operation.messages) {
                            messages = operation.messages;
                            sticky = true;
                        }
                    }
                }
                if (messages.length > 0) {
                    messages.forEach( function(message){
                        me.createGrowlMessage(title, message, sticky);
                    });
                } else {
                    me.createGrowlMessage(title, message, sticky);
                }

                list.setLoading(false);
                list.store.load();
            }
        });
    },

    /**
     * Helper to show a growl message
     *
     * @param title
     * @param message
     */
    createGrowlMessage: function(title, message, sticky) {
        var me = this,
            win = me.getWindow();

        if (!sticky) {
            Shopware.Notification.createGrowlMessage(title, message, win.title);
        } else {
            Shopware.Notification.createStickyGrowlMessage({
                title: title,
                text: message,
                width: 400
            });
        }
    },

    /**
     * Callback function to set the window title depending on the current navigation entry
     *
     * @param tree
     * @param node
     */
    onSelectNavigationEntry: function(tree, node) {
        var me = this,
            panel = me.getPanel(),
            win = me.getWindow(),
            item = node.get('id'),
            layout = panel.getLayout();

        layout.setActiveItem(item);
        win.loadTitle(node);
    },

    /**
     * Callback function to save the configuration form
     *
     * @param button
     */
    onSaveConfigForm: function(btn) {
        var me = this;
            form = btn.up('form');

        form.setLoading();

        if (form.getRecord()) {
            var model = form.getRecord();

            form.getForm().updateRecord(model);
            model.save({
                success: function(record) {
                    form.setLoading(false);
                    Shopware.Notification.createGrowlMessage('{s name=success}Success{/s}', '{s name=config/success/message}Successfully applied changes{/s}');
                },
                failure: function(record) {
                    form.setLoading(false);
                    var rawData = record.getProxy().getReader().rawData,
                        message = rawData.message;
                    Shopware.Notification.createGrowlMessage('{s name=error}Error{/s}', message);
                }
            });
        }
    },

    /**
     * Callback function to save the import configuration form
     *
     * @param button
     */
    onSaveImportConfigForm: function(btn) {
        var me = this;
        form = btn.up('form');

        form.setLoading();
        if (form.getRecord()) {
            var model = form.getRecord();

            form.getForm().updateRecord(model);
            model.save({
                success: function(record) {
                    form.setLoading(false);
                    var unitsStore = me.getUnitsMapping().getStore();
                    if (unitsStore.getUpdatedRecords().length < 1) {
                        me.createGrowlMessage('{s name=success}Success{/s}', '{s name=config/success/message}Successfully applied changes{/s}');
                        return;
                    }

                    unitsStore.sync({
                        success :function (records, operation) {
                            me.createGrowlMessage('{s name=success}Success{/s}', '{s name=config/success/message}Successfully applied changes{/s}');
                        },
                        failure:function (batch) {
                            me.createGrowlMessage('{s name=error}Error{/s}','{s name=config/units/error_save_message}Mapping der Einheiten konnte nicht gespeichert werden.{/s}');
                        }
                    });
                },
                failure: function(record) {
                    form.setLoading(false);
                    var rawData = record.getProxy().getReader().rawData,
                        message = rawData.message;
                    Shopware.Notification.createGrowlMessage('{s name=error}Error{/s}', response.responseText);
                }
            });
        }
    },

    /**
     * Reload Shopware Connect units store
     * and show/hide already assigned units
     */
    onHideAssignedUnits: function(checkbox, value)
    {
        var me = this;
        var store = me.getUnitsMapping().getStore();
        var hideAssigned = 0;

        if (value === true) {
            hideAssigned = 1;
        }

        store.load({
            params: {
                'hideAssignedUnits': hideAssigned
            }
        });
    },

    onAdoptUnits: function(btn) {
        var me = this;
        var form = btn.up('form');

        Ext.Msg.show({
            title: me.messages.adoptUnitsTitle,
            msg: me.messages.adoptUnitsMessage,
            buttons: Ext.Msg.YESNO,
            fn: function(response) {
                if(response !== 'yes') {
                    return;
                }

                form.setLoading();
                Ext.Ajax.request({
                    url: '{url controller=ConnectConfig action=adoptUnits}',
                    method: 'POST',
                    success: function(response, opts) {
                        var responseObject = Ext.decode(response.responseText);
                        form.setLoading(false);
                        if (responseObject.success) {
                        } else {
                        }
                    }
                });
            }
        });
    },

    /**
     * Callback function to save the export configuration form
     *
     * @param button
     */
    onSaveExportConfigForm: function(btn) {
        var me = this;
        form = btn.up('form');

        form.setLoading();
        if (form.getRecord()) {
            var model = form.getRecord();

            form.getForm().updateRecord(model);
            model.save({
                success: function(record) {
                    form.setLoading(false);
                    Shopware.Notification.createGrowlMessage('{s name=success}Success{/s}', '{s name=config/success/message}Successfully applied changes{/s}');
                },
                failure: function(record) {
                    form.setLoading(false);
                    var rawData = record.getProxy().getReader().rawData,
                        message = rawData.message;
                    Shopware.Notification.createGrowlMessage('{s name=error}Error{/s}', message);
                }
            });
        }
    },

    /**
     * Sends marketplace attributes mapping to the PHP
     */
    saveMarketplaceAttributesMapping: function() {
        var me = this,
            panel = me.getMarketeplaceMappingPanel(),
            store = me.getMarketeplaceMapping().localProductAttributesStore;

        if (store.getNewRecords().length > 0 || store.getUpdatedRecords().length > 0 || store.getRemovedRecords().length > 0) {
            panel.setLoading();
            store.sync({
                success :function (records, operation) {
                    panel.setLoading(false);
                    me.createGrowlMessage('{s name=success}Success{/s}', '{s name=config/success/message}Änderungen erfolgreich übernommen{/s}');
                },
                failure:function (batch) {
                    panel.setLoading(false);
                    me.createGrowlMessage('{s name=error}Error{/s}', batch.proxy.getReader().jsonData.message);
                }
            });
        }
    },

    /**
     * Calculate needed time to finish synchronization
     *
     * @param progressBar
     */
    onCalculateFinishTime: function(progressBar) {
        var me = this;
        me.time = 0;
        me.resetSpentTime();

        Ext.TaskManager.start({
            interval: 60000,
            run: function() {
                Ext.Ajax.request({
                    url: '{url controller=ConnectConfig action=calculateFinishTime}',
                    method: 'POST',
                    success: function(response, opts) {
                        var responseObject = Ext.decode(response.responseText);

                        if (responseObject.time > 0) {
                            if ((responseObject.time - (me.time + me.spentTime)) == 0) {
                                return;
                            }
                            me.time = responseObject.time;
                            me.resetSpentTime();

                            var time = me.secondsToTime(me.time);
                            progressBar.wait({
                                interval: 500,
                                increment: 15,
                                scope: this
                            });
                            progressBar.updateText(me.messages.updatePartOneMessage + ' ' + time.h + ' ' + me.messages.hours + ' ' + time.m + ' ' + me.messages.minutes + ' ' + time.s + ' ' + me.messages.seconds + ' ' + me.messages.updatePartTwoMessage);
                        } else {
                            me.resetSpentTime();
                            me.resetTime();
                            progressBar.reset();
                            progressBar.updateText(me.messages.doneMessage);
                        }
                    }
                });
            }
        });

        Ext.TaskManager.start({
            interval: 5000,
            run: function () {
                if (me.time >= 5) {
                    me.time = me.time - 5;
                    me.spentTime = me.spentTime + 5;
                    var time = me.secondsToTime(me.time);
                    progressBar.updateText(me.messages.updatePartOneMessage + ' ' + time.h + ' ' + me.messages.hours + ' ' + time.m + ' ' + me.messages.minutes + ' ' + time.s + ' ' + me.messages.seconds + ' ' + me.messages.updatePartTwoMessage);
                } else {
                    progressBar.reset();
                    progressBar.updateText(me.messages.doneMessage);
                }
            }
        });

    },

    onChangeLogging: function(checkbox, newValue, oldValue) {
        var me = this;
        var loggingEnabled = 0;
        if (newValue === true) {
            loggingEnabled = 1;
        }

        Ext.Ajax.request({
            url: '{url controller=ConnectConfig action=changeLogging}',
            method: 'POST',
            params: {
                enableLogging: loggingEnabled
            },
            success: function(response, opts) {
                var data = Ext.JSON.decode(response.responseText);
                if (data.success == false) {
                    me.createGrowlMessage('{s name=error}Error{/s}', '{s name=config/log_label}Logging aktivieren{/s}');
                }
            }
        });
    },

    /**
     * On change customer group for price configuration
     * reload the store with available price fields
     *
     * @param combo
     * @param newValue
     * @param oldValue
     */
    onChangePriceGroupForPrice: function(combo, newValue, oldValue) {
        if (!oldValue) {
            return;
        }

        var me = this;
        var exportConfigForm = me.getExportConfigForm();
        exportConfigForm.setLoading(true);
        exportConfigForm.priceFieldForPrice.store.load({
            params: {
                'customerGroup': newValue
            },
            callback: function() {
                exportConfigForm.setLoading(false);
            }
        });
    },

    /**
     * On change customer group for purchase price configuration
     * reload the store with available price fields
     *
     * @param combo
     * @param newValue
     * @param oldValue
     */
    onChangePriceGroupForPurchasePrice: function(combo, newValue, oldValue) {
        if (!oldValue) {
            return;
        }

        var me = this;
        var exportConfigForm = me.getExportConfigForm();
        exportConfigForm.setLoading(true);
        exportConfigForm.priceFieldForPurchasePrice.store.load({
            params: {
                'customerGroup': newValue
            },
            callback: function() {
                exportConfigForm.setLoading(false);
            }
        });
    },

    /**
     * Checks price field selection, if it's not supported
     * shows error message
     *
     * @param combo
     * @param newValue
     * @param oldValue
     */
    onChangePriceFieldForPurchasePrice: function(combo, newValue, oldValue) {
        if (!oldValue) {
            return;
        }

        var me = this;

        var newRecord = combo.findRecordByValue(newValue);
        if (newRecord && newRecord.get('available') === false) {
            me.createGrowlMessage('{s name=error}Error{/s}', me.messages.priceFieldIsNotSupported);
        }
    },

    /**
     * Checks price field selection, if it's not supported
     * shows error message
     *
     * @param combo
     * @param newValue
     * @param oldValue
     */
    onChangePriceFieldForPrice: function(combo, newValue, oldValue) {
        if (!oldValue) {
            return;
        }

        var me = this;

        var newRecord = combo.findRecordByValue(newValue);
        if (newRecord && newRecord.get('available') === false) {
            me.createGrowlMessage('{s name=error}Error{/s}', me.messages.priceFieldIsNotSupported);
        }
    },

    /**
     * Convert number of seconds into time object
     *
     * @param integer secs Number of seconds to convert
     * @return object
     */
    secondsToTime: function (secs) {
        var hours = parseInt( secs / 3600 ) % 24;
        var minutes = parseInt( secs / 60 ) % 60;
        var seconds = secs % 60;

        var obj = {
            "h": hours,
            "m": minutes,
            "s": seconds
        };
        return obj;
    },

    /**
     * Reset spent time used for synchronization bar
     */
    resetSpentTime: function() {
        var me = this;
        me.spentTime = 0;
    },

    /**
     * Reset time used for synchronization bar
     */
    resetTime: function() {
        var me = this;
        me.time = 0;
    }
});
//{/block}
