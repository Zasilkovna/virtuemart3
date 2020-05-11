jQuery.fn.extend({
	createRepeater: function (options = {}) {
		var hasOption = function (optionKey) {
			return options.hasOwnProperty(optionKey);
		};

		var option = function (optionKey) {
			return options[optionKey];
		};

		var generateId = function (string) {
			return string
				.replace(/\[/g, '_')
				.replace(/\]/g, '')
				.toLowerCase();
		};

		var addItem = function (items, key, fresh = true) {
			var itemContent = items;
			var group = itemContent.data("group");
			var item = itemContent;
			var input = item.find('input,select');

			input.each(function (index, el) {
				var attrName = jQuery(el).data('name');
				var skipName = jQuery(el).data('skip-name');
				if (skipName != true) {
					jQuery(el).attr("name", group + "[" + key + "]" + "[" + attrName + "]");
				} else {
					if (attrName != 'undefined') {
						jQuery(el).attr("name", attrName);
					}
				}
				/*
				if (fresh == true) {
					jQuery(el).attr('value', '');
				}
				*/

				jQuery(el).attr('id', generateId(jQuery(el).attr('name')));
				jQuery(el).parent().find('label').attr('for', generateId(jQuery(el).attr('name')));
			})

			var itemClone = items;

			/* Handling remove btn */
			var removeButton = itemClone.find('.remove-btn');

			if (key == 0) {
				removeButton.attr('disabled', true);
			} else {
				removeButton.attr('disabled', false);
			}

			removeButton.attr('onclick', 'jQuery(this).parents(\'.items\').remove()');

			var newItem = jQuery("<div class='items'>" + itemClone.html() + "<div/>");
			newItem.attr('data-index', key)

			newItem.appendTo(repeater);
		};

		/* find elements */
		var repeater = this;
		var items = repeater.find(".items");
		var key = 0;
		var addButton = repeater.find('.repeater-add-btn');

		items.each(function (index, item) {
			items.remove();
			if (hasOption('showFirstItemToDefault') && option('showFirstItemToDefault') == true) {
				addItem(jQuery(item), key);
				key++;
			} else {
				if (items.length > 1) {
					addItem(jQuery(item), key);
					key++;
				}
			}
		});

		/* handle click and add items */
		addButton.on("click", function () {
			addItem(jQuery(items[0]), key);
			key++;
		});
	}
});
