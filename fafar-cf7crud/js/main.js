window.addEventListener("DOMContentLoaded", () => {
  // DOM ready! Images, frames, and other subresources are still downloading.
  // Set listener to custom file input button
  fafarCf7CrudSetButtonListener();

  // Set listener to stock input
  fafarCf7CrudSetStockFileInputListener();
});

function fafarCf7CrudSetButtonListener() {
  const buttons = document.querySelectorAll(
    "button.fafar-cf7crud-input-document-button"
  );

  buttons.forEach((btn) => {
    btn.addEventListener("click", function () {
      const attr_name = btn.getAttribute("data-file-input-button");

      const input = document.querySelector('input[name="' + attr_name + '"]');
      if (input) input.click();
    });
  });
}

function fafarCf7CrudSetStockFileInputListener() {
  const fafar_cf7crud_stock_file_input = document.querySelectorAll(
    "input.fafar-cf7crud-stock-file-input"
  );

  fafar_cf7crud_stock_file_input.forEach((input) => {
    input.addEventListener("change", fafarCf7OnChangeCrudInputHandler);
  });
}

function fafarCf7OnChangeCrudInputHandler(event) {
  const attr_name = event.target.getAttribute("name");

  const fileName = this.files[0] ? this.files[0].name : "";
  this.setAttribute("value", fileName);

  document
    .querySelector(
      'input[name="fafar-cf7crud-input-file-hidden-' + attr_name + '"]'
    )
    .setAttribute("value", fileName);

  const span = document.querySelector(
    'span[data-file-input-label="' + attr_name + '"]'
  );
  if (span) span.textContent = fileName ? fileName : "Selecione um arquivo";
}
