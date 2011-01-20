/**
 * Code needed to clone nested relations forms
 * @plugin pxPropelEasyEmbeddedRelationsPlugin
 * @author Jeremie Augustin <jeremie dot augustin at pixel-cookers dot com>
 * @author Krzysztof Kotowicz <kkotowicz at gmail dot com>
 */
(function($) {

	/**
	 * Increments field IDs and names by one
	 */
	$.fn.incrementFields = function(container) {
		return this.each(function() {
			var nameRe = new RegExp('\\[' + container + '\\]\\[(\\d+)\\]');
			var idRe = new RegExp('_' + container + '_(\\d+)_');

			$(this).find(':input').each(function() { // find each input
				var matches;
				if (matches = nameRe.exec(this.name)) { // check if its name contains [container][number]
					// if so, increase the number in field name
					this.name = this.name.replace(nameRe,'[' + container + '][' + (parseInt(matches[1],10)+1) + ']');
				}
				if (matches = idRe.exec(this.id)) { // check if its name contains _container_number_
					// if so, increase the number in label for attribute
					this.id = this.id.replace(idRe,'_' + container + '_' + (parseInt(matches[1],10)+1) + '_');
				}
				$(this).trigger('change.px'); // trigger onchange event just for a case
			}).end();

			// fix labels
			$(this).find('label[for]').each(function() {
				var matches;
				if (matches = idRe.exec($(this).attr('for'))) { // check if its name contains _container_number_
					// if so, increase the number in label for attribute
					$(this).attr('for', $(this).attr('for').replace(idRe,'_' + container + '_' + (parseInt(matches[1],10)+1) + '_'));
				}
			});

			// increase the number in first <th>
			$header = $(this).children('th').eq(0);
			if ($header.text().match(/^\d+$/)) {
				$header.text(parseInt($header.text(),10) + 1);
			}
			$(this).end();
		});
	}

})(jQuery);

jQuery(function($) {

	// when clicking the 'add relation' button
	$('.pxAddRelation').click(function() {

		// find last row of my siblings (each row represents a subform)
		$row = $(this).closest('tr,li').siblings('tr:last,li:last');

		// clone it, increment the fields and insert it below, additionally triggering events
		$row.trigger('beforeclone.px');
		var $newrow = $row.clone(true);
		$row.trigger('afterclone.px')

		$newrow
			.incrementFields($(this).attr('rel'))
			.trigger('beforeadd.px')
			.insertAfter($row)
			.trigger('afteradd.px');

		//use events to further modify the cloned row like this
		// $(document).bind('beforeadd.px', function(event) { $(event.target).hide() /* event.target is cloned row */ });
		// $(document).bind('afteradd.px', function(event) { });
	})
});