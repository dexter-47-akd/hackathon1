// Main JavaScript functionality
document.addEventListener("DOMContentLoaded", () => {
  initializeSearch()
  initializeCategoryFilter()
})

// Search functionality
function initializeSearch() {
  const searchInput = document.getElementById("searchInput")
  if (searchInput) {
    searchInput.addEventListener("input", function () {
      const searchTerm = this.value.toLowerCase()
      filterStalls(searchTerm)
    })
  }
}

// Category filter functionality
function initializeCategoryFilter() {
  const categoryBtns = document.querySelectorAll(".category-btn")
  categoryBtns.forEach((btn) => {
    btn.addEventListener("click", function (e) {
      e.preventDefault()

      // Remove active class from all buttons
      categoryBtns.forEach((b) => b.classList.remove("active"))

      // Add active class to clicked button
      this.classList.add("active")

      // Filter stalls by category
      const category = this.dataset.category
      filterStallsByCategory(category)
    })
  })
}

// Filter stalls by search term
function filterStalls(searchTerm) {
  const stallCards = document.querySelectorAll(".stall-card")
  stallCards.forEach((card) => {
    const shopName = card.querySelector("h3").textContent.toLowerCase()
    const category = card.querySelector("p").textContent.toLowerCase()
    const location = card.querySelectorAll("p")[1].textContent.toLowerCase()

    if (shopName.includes(searchTerm) || category.includes(searchTerm) || location.includes(searchTerm)) {
      card.style.display = "block"
    } else {
      card.style.display = "none"
    }
  })
}

// Filter stalls by category
function filterStallsByCategory(category) {
  const stallCards = document.querySelectorAll(".stall-card")
  stallCards.forEach((card) => {
    const stallCategory = card.querySelector("p").textContent.toLowerCase()

    if (category === "indian" && stallCategory.includes("indian")) {
      card.style.display = "block"
    } else if (category === "mexican" && stallCategory.includes("mexican")) {
      card.style.display = "block"
    } else if (category === "asian" && stallCategory.includes("asian")) {
      card.style.display = "block"
    } else if (category === "bbq" && stallCategory.includes("bbq")) {
      card.style.display = "block"
    } else if (category === "desserts" && stallCategory.includes("dessert")) {
      card.style.display = "block"
    } else {
      card.style.display = "none"
    }
  })
}

// View stall details
function viewStallDetails(vendorId) {
  window.location.href = `stall-details.php?id=${vendorId}`
}

// Utility functions
function showAlert(message, type = "info") {
  const alertDiv = document.createElement("div")
  alertDiv.className = `alert alert-${type}`
  alertDiv.innerHTML = message

  document.body.insertBefore(alertDiv, document.body.firstChild)

  setTimeout(() => {
    alertDiv.remove()
  }, 5000)
}
