//{namespace name=backend/connect/view/main}

//{block name="backend/connect/view/import/own_categories"}
Ext.define('Shopware.apps.Connect.view.import.OwnCategories', {
    extend: 'Ext.tree.Panel',
    alias: 'widget.connect-own-categories',

    border: true,
    rootVisible: false,
    width: 400,
    height: 300,
    root: {
        id: 1,
        expanded: true
    },
    store: 'base.CategoryTree',
    name: 'localCategoryTree',
    viewConfig: {
        plugins: {
            ptype: 'treeviewdragdrop',
            pluginId: 'treeviewdragdrop',
            appendOnly: true,
            dragGroup: 'remote-category',
            dropGroup: 'local-category'
        },
        listeners: {
            viewready: function (tree) {
                var view = tree,
                    dd = view.getPlugin('treeviewdragdrop');

                dd.dropZone.isValidDropPoint = function(node, position, dragZone, e, data) {
                    var targetRecord = view.getRecord(node),
                        draggedRecord = data.records[0];

                    //its minus two, cause we have contact and stream node
                    var draggedDepth = draggedRecord.data.depth - 2;

                    //its plus one, cause we want the parent node depth
                    var parentDepth = targetRecord.data.depth + 1;

                    //dragged leaf can be drop everywhere except on the main categories (deutsch, english)
                    if(draggedRecord.data.leaf && !targetRecord.data.leaf && parentDepth > 2){
                        return true;
                    }

                    return !targetRecord.data.leaf && draggedDepth == parentDepth;
                };
            }
        }
    },

    snippets: {
        reload: '{s name=import/tree/reload}Neuladen{/s}'
    },

    initComponent: function() {
        var me = this;

        me.on({
            // Context menu on items
            itemcontextmenu: me.onOpenItemContextMenu,
            // Context menu on container
            containercontextmenu: me.onOpenContainerContextMenu,
            // scope
            scope: me
        });

        me.callParent(arguments);
    },

    /**
     * Event listener method which fires when the user performs a right click
     * on the Ext.tree.Panel.
     *
     * Opens a context menu which features functions to reload the category list.
     *
     * Fires the following events on the Ext.tree.Panel:
     * - reload
     *
     * @event containercontextmenu
     * @param [object] view - HTML DOM Object of the Ext.tree.Panel
     * @param [object] event - The fired Ext.EventObject
     * @return void
     */
    onOpenContainerContextMenu : function(view, event) {
        event.preventDefault(true);
        var me = this,
            menuElements = [];

        menuElements.push({
            text: me.snippets.reload,
            iconCls:'sprite-arrow-circle-315',
            handler:function () {
                me.fireEvent('reloadOwnCategories', me, view);
            }
        });

        var menu = Ext.create('Ext.menu.Menu', {
            items:menuElements
        });
        menu.showAt(event.getPageX(), event.getPageY());
    },

    /**
     * Event listener method which fires when the user performs a right click
     * on a node in the Ext.tree.Panel.
     *
     * Opens a context menu which features functions to reload the tree.
     *
     * Fires the following events on the Ext.tree.Panel:
     * - reload
     *
     * @event itemcontextmenu
     * @param [object] view - HTML DOM Object of the Ext.tree.Panel
     * @param [object] record - Associated Ext.data.Model for the clicked node
     * @param [object] item HTML DOM Object of the clicked node
     * @param [integer] index - Index of the clicked node in the associated Ext.data.TreeStore
     * @param [object] event - The fired Ext.EventObject
     * @return void
     */
    onOpenItemContextMenu : function(view, record, item, index, event) {
        event.preventDefault(true);
        var me = this,
            menuElements = [];

        menuElements.push({
            text: me.snippets.reload,
            iconCls:'sprite-arrow-circle-315',
            handler:function () {
                me.fireEvent('reloadOwnCategories', me, view);
            }
        });

        var menu = Ext.create('Ext.menu.Menu', {
            items: menuElements
        });
        menu.showAt(event.getPageX(), event.getPageY());
    }
});
//{/block}