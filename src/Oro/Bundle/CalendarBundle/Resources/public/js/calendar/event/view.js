/*global define*/
define(['underscore', 'backbone', 'orotranslation/js/translator', 'oro/dialog-widget', 'oroui/js/loading-mask',
    'orocalendar/js/form-validation', 'oroui/js/delete-confirmation', 'orocalendar/js/calendar/event/model'
    ], function (_, Backbone, __, DialogWidget, LoadingMask, FormValidation, DeleteConfirmation, EventModel) {
    'use strict';

    var $ = Backbone.$;

    /**
     * @export  orocalendar/js/calendar/event/view
     * @class   orocalendar.calendar.event.View
     * @extends Backbone.View
     */
    return Backbone.View.extend({
        /** @property {Object} */
        selectors: {
            loadingMaskContent: '.loading-content'
        },

        options: {
            formTemplateSelector: null,
            calendar: null
        },

        initialize: function () {
            var templateHtml = $(this.options.formTemplateSelector).html();
            this.template = _.template(templateHtml);

            this.listenTo(this.model, 'sync', this.onModelSave);
            this.listenTo(this.model, 'destroy', this.onModelDelete);
        },

        remove: function () {
            this.trigger('remove');
            this._hideMask();
            Backbone.View.prototype.remove.apply(this, arguments);
        },

        onModelSave: function () {
            this.trigger('addEvent', this.model);
            this.eventDialog.remove();
            this.remove();
        },

        onModelDelete: function () {
            this.eventDialog.remove();
            this.remove();
        },

        render: function () {
            var modelData, eventForm, onDelete;
            // create a dialog
            if (!this.model) {
                this.model = new EventModel();
            }
            modelData = this.model.toJSON();
            eventForm = this.template(modelData);
            eventForm = this.fillForm(eventForm, modelData);

            this.eventDialog = new DialogWidget({
                el: eventForm,
                title: this.model.isNew() ? __('Add New Event') : __('Edit Event'),
                stateEnabled: false,
                incrementalPosition: false,
                loadingMaskEnabled: false,
                dialogOptions: {
                    modal: true,
                    resizable: false,
                    width: 475,
                    autoResize: true,
                    close: _.bind(this.remove, this)
                },
                submitHandler: _.bind(function () {
                    this.saveModel();
                }, this)
            });
            this.eventDialog.render();

            // subscribe to 'delete event' event
            onDelete = _.bind(function (e) {
                var el, confirm;
                e.preventDefault();
                el = $(e.target);
                confirm = new DeleteConfirmation({
                    content: el.data('message')
                });
                confirm.on('ok', _.bind(this.deleteModel, this));
                confirm.open();
            }, this);
            this.eventDialog.getAction('delete', 'adopted', function (deleteAction) {
                deleteAction.on('click', onDelete);
            });

            // init loading mask control
            this.loadingMask = new LoadingMask();
            this.eventDialog.$el.closest('.ui-dialog').append(this.loadingMask.render().$el);

            return this;
        },

        saveModel: function () {
            this.showSavingMask();
            try {
                var data = this.getEventFormData();
                data.calendar = this.options.calendar;

                this.model.save(data, {
                    wait: true,
                    error: _.bind(this._handleResponseError, this)
                });
            } catch (err) {
                this.showError(err);
            }
        },

        deleteModel: function () {
            this.showDeletingMask();
            try {
                this.model.destroy({
                    wait: true,
                    error: _.bind(this._handleResponseError, this)
                });
            } catch (err) {
                this.showError(err);
            }
        },

        showSavingMask: function () {
            this._showMask(__('Saving...'));
        },

        showDeletingMask: function () {
            this._showMask(__('Deleting...'));
        },

        _showMask: function (message) {
            if (this.loadingMask) {
                this.loadingMask.$el
                    .find(this.selectors.loadingMaskContent)
                    .text(message);
                this.loadingMask.show();
            }
        },

        _hideMask: function () {
            if (this.loadingMask) {
                this.loadingMask.hide();
            }
        },

        _handleResponseError: function (model, response) {
            this.showError(response.responseJSON);
        },

        showError: function (err) {
            this._hideMask();
            if (this.eventDialog) {
                FormValidation.handleErrors(this.eventDialog.$el.parent(), err);
            }
        },

        fillForm: function (form, modelData) {
            form = $(form);
            _.each(modelData, function (value, key) {
                var input = form.find('[name$="[' + key + ']"]');
                if (input.length) {
                    if (input.is(':checkbox')) {
                        input.prop('checked', value);
                    } else {
                        input.val(value);
                    }
                    input.change();
                }
            });
            return form;
        },

        getEventFormData: function () {
            var self = this;
            var fieldNameRegex = /\[(\w+)\]/g,
                data = {},
                formData = this.eventDialog.form.serializeArray();
            formData = formData.concat(this.eventDialog.form.find('input[type=checkbox]:not(:checked)')
                .map(function () {
                    return {"name": this.name, "value": false};
                }).get());
            _.each(formData, function (dataItem) {
                var matches = [], match;
                while ((match = fieldNameRegex.exec(dataItem.name)) !== null) {
                    matches.push(match[1]);
                }

                if (matches.length) {
                    self.setValueByPath(data, dataItem.value, matches);
                }
            });

            return data;
        },

        setValueByPath: function (obj, value, path) {
            var parent = obj, candidate;

            for (var i = 0; i < path.length - 1; i += 1) {
                candidate = parent[path[i]];
                if (candidate === undefined) {
                    candidate = {};
                    parent[path[i]] = candidate;
                }

                parent = candidate;
            }

            parent[path[path.length - 1]] = value;
        }
    });
});
