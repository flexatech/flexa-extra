/**
 * Editor UI for the Flexa Extra "Options configurator" block.
 *
 * Dependency-free of any build step: it uses the global `wp.*` packages that
 * WordPress already ships. The block is dynamic (front-end markup is produced
 * server-side by ProductRenderer::render_configurator()), so save() returns null
 * and edit() only renders a picker + placeholder.
 */
(function (wp) {
  'use strict';

  if (!wp || !wp.blocks) {
    return;
  }

  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var useState = wp.element.useState;
  var useEffect = wp.element.useEffect;
  var __ = wp.i18n.__;
  var sprintf = wp.i18n.sprintf;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var PanelBody = wp.components.PanelBody;
  var SelectControl = wp.components.SelectControl;
  var TextControl = wp.components.TextControl;
  var Placeholder = wp.components.Placeholder;
  var Spinner = wp.components.Spinner;

  wp.blocks.registerBlockType('flexa-extra/configurator', {
    edit: function (props) {
      var attributes = props.attributes;
      var setAttributes = props.setAttributes;

      var setsState = useState(null);
      var sets = setsState[0];
      var setSets = setsState[1];

      useEffect(function () {
        wp.apiFetch({ path: 'flexa-extra/v1/option-sets' })
          .then(function (res) {
            var data = res && res.data ? res.data : res;
            setSets(Array.isArray(data) ? data : []);
          })
          .catch(function () {
            setSets([]);
          });
      }, []);

      var options = [{ label: __('Select an option set…', 'flexa-extra'), value: 0 }].concat(
        (sets || []).map(function (s) {
          return { label: s.name || '#' + s.id, value: s.id };
        })
      );

      var instructions = attributes.optionSetId
        ? sprintf(
            /* translators: %d: option set id. */
            __('Renders option set #%d on the front end. Display only; selections are not added to the cart.', 'flexa-extra'),
            attributes.optionSetId
          )
        : __('Pick an option set in the block settings. Display only; this configurator is not wired to add-to-cart.', 'flexa-extra');

      return el(
        Fragment,
        {},
        el(
          InspectorControls,
          {},
          el(
            PanelBody,
            { title: __('Configurator', 'flexa-extra'), initialOpen: true },
            el(SelectControl, {
              label: __('Option set', 'flexa-extra'),
              value: attributes.optionSetId,
              options: options,
              onChange: function (v) {
                setAttributes({ optionSetId: parseInt(v, 10) || 0 });
              },
            }),
            el(TextControl, {
              label: __('Base product ID (optional)', 'flexa-extra'),
              help: __('Used only so percentage prices have a base to compute against.', 'flexa-extra'),
              type: 'number',
              value: attributes.productId || '',
              onChange: function (v) {
                setAttributes({ productId: parseInt(v, 10) || 0 });
              },
            })
          )
        ),
        el(
          'div',
          useBlockProps(),
          el(
            Placeholder,
            {
              icon: 'forms',
              label: __('Flexa Extra – Options configurator', 'flexa-extra'),
              instructions: instructions,
            },
            sets === null ? el(Spinner) : null
          )
        )
      );
    },
    // Dynamic block: rendered by PHP.
    save: function () {
      return null;
    },
  });
})(window.wp);
