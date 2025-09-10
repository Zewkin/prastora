document.addEventListener("DOMContentLoaded", function () {
  const faqItems = document.querySelectorAll(".faq_item");

  faqItems.forEach((item) => {
    const title = item.querySelector(".faq_item_title");
    title.addEventListener("click", () => {
      item.classList.toggle("faq_item_active");
    });
  });
});
