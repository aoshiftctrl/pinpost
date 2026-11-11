(function (wp, cfg) {
  var el = wp.element.createElement;
  var useEffect = wp.element.useEffect;
  var useSelect = wp.data.useSelect;
  var useDispatch = wp.data.useDispatch;
  var CheckboxControl = wp.components.CheckboxControl;
  var PluginPostStatusInfo = wp.editor.PluginPostStatusInfo;

  function PinPost() {
    var data = useSelect(function (select) {
      var ed = select('core/editor');
      return {
        id: ed.getCurrentPostId(),
        type: ed.getCurrentPostType(),
        // Ein Datum in der Zukunft speichert der Server als "future".
        published: ed.getEditedPostAttribute('status') === 'publish' && !ed.isEditedPostBeingScheduled(),
        pinned: !!ed.getEditedPostAttribute('shft_pinned'),
        saved: !!ed.getCurrentPostAttribute('shft_pinned')
      };
    }, []);
    var editPost = useDispatch('core/editor').editPost;

    var pinned = data.pinned;
    var published = data.published;
    var other = cfg.id && cfg.id !== data.id;
    var stale = pinned && !data.saved && !published;

    // Ein noch ungespeicherter Pin wird verworfen, sobald der Beitrag nicht
    // mehr veroeffentlicht waere - der Server lehnt sonst das ganze Speichern
    // ab. Ein gespeicherter Pin bleibt stehen und wird erst serverseitig beim
    // Speichern entfernt, sonst ginge er beim Zuruecksetzen des Status verloren.
    useEffect(function () {
      if (stale) {
        editPost({ shft_pinned: false }, { undoIgnore: true });
      }
    }, [stale]);

    // Im Beitragseditor kann auch ein Template oder Pattern bearbeitet werden.
    if (data.type !== 'post') {
      return null;
    }

    var help = '';
    if (other) {
      help = 'Bereits gepinnt: „' + cfg.title + '“. Erst dort lösen.';
    } else if (!published) {
      help = 'Nur für veröffentlichte Beiträge.';
    }

    return el(PluginPostStatusInfo, { className: 'shft-pin-post' },
      el(CheckboxControl, {
        label: 'In Kategorie anpinnen',
        help: help,
        checked: pinned,
        disabled: !!other || (!published && !pinned),
        onChange: function (val) {
          editPost({ shft_pinned: val });
        }
      })
    );
  }

  wp.plugins.registerPlugin('shft-pin-post', { render: PinPost });
})(window.wp, window.shftPinPost || {});
