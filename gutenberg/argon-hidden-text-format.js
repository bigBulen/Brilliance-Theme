(function (wp) {
  if (!wp || !wp.richText) return;

  const { registerFormatType, toggleFormat, removeFormat } = wp.richText;
  const blockEditor = wp.blockEditor || wp.editor;
  if (!blockEditor || !blockEditor.RichTextToolbarButton) return;

  const { RichTextToolbarButton } = blockEditor;
  const el = wp.element.createElement;

  const FORMAT_BG = "argon/hidden-text-background";
  const FORMAT_BLUR = "argon/hidden-text-blur";

  const CLASS_BG = "argon-hidden-text argon-hidden-text-background";
  const CLASS_BLUR = "argon-hidden-text argon-hidden-text-blur";

  function makeButton({ format, title, icon, removeFormats, applyClass }) {
    return function Edit({ isActive, value, onChange }) {
      return el(RichTextToolbarButton, {
        icon,
        title,
        isActive,
        onClick: function () {
          let next = value;
          (removeFormats || []).forEach((f) => {
            next = removeFormat(next, f);
          });
          next = toggleFormat(next, {
            type: format,
            attributes: {
              class: applyClass,
            },
          });
          onChange(next);
        },
      });
    };
  }

  // 注意：registerFormatType 的 className 只能是单个 class token（不能带空格）
  registerFormatType(FORMAT_BG, {
    title: "纯黑隐藏",
    tagName: "span",
    className: "argon-hidden-text-background",
    edit: makeButton({
      format: FORMAT_BG,
      title: "纯黑隐藏",
      icon: "hidden",
      removeFormats: [FORMAT_BLUR], // 保持两者互斥，避免叠加/嵌套
      applyClass: CLASS_BG,
    }),
  });

  registerFormatType(FORMAT_BLUR, {
    title: "高斯模糊隐藏",
    tagName: "span",
    className: "argon-hidden-text-blur",
    edit: makeButton({
      format: FORMAT_BLUR,
      title: "高斯模糊隐藏",
      icon: "filter",
      removeFormats: [FORMAT_BG],
      applyClass: CLASS_BLUR,
    }),
  });
})(window.wp);