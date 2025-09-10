document.addEventListener("DOMContentLoaded", function () {
  const consItems = document.querySelectorAll(".articles_list_item");

  consItems.forEach((item) => {
    const title = item.querySelector(".articles_list_item_title");
    title.addEventListener("click", () => {
      item.classList.toggle("articles_list_item_active");
    });
  });
});
