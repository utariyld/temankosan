// Testimonial functionality
class TestimonialManager {
  constructor() {
    this.apiUrl = "/api/testimonials.php"
    this.init()
  }

  init() {
    this.loadTestimonials()
    this.setupEventListeners()
  }

  setupEventListeners() {
    // Floating button click
    const floatingBtn = document.getElementById("testimonial-floating-btn")
    if (floatingBtn) {
      floatingBtn.addEventListener("click", () => {
        this.showTestimonialModal()
      })
    }

    // Modal close events
    const modal = document.getElementById("testimonial-modal")
    const closeBtn = document.getElementById("close-testimonial-modal")
    const cancelBtn = document.getElementById("cancel-testimonial")

    if (closeBtn) {
      closeBtn.addEventListener("click", () => {
        this.hideTestimonialModal()
      })
    }

    if (cancelBtn) {
      cancelBtn.addEventListener("click", () => {
        this.hideTestimonialModal()
      })
    }

    // Click outside modal to close
    if (modal) {
      modal.addEventListener("click", (e) => {
        if (e.target === modal) {
          this.hideTestimonialModal()
        }
      })
    }

    // Form submission
    const form = document.getElementById("testimonial-form")
    if (form) {
      form.addEventListener("submit", (e) => {
        e.preventDefault()
        this.submitTestimonial()
      })
    }

    // Rating stars
    this.setupRatingStars()
  }

  setupRatingStars() {
    const stars = document.querySelectorAll(".rating-star")
    stars.forEach((star, index) => {
      star.addEventListener("click", () => {
        this.setRating(index + 1)
      })

      star.addEventListener("mouseenter", () => {
        this.highlightStars(index + 1)
      })
    })

    const ratingContainer = document.querySelector(".rating-container")
    if (ratingContainer) {
      ratingContainer.addEventListener("mouseleave", () => {
        const currentRating = document.getElementById("rating-input").value || 5
        this.highlightStars(currentRating)
      })
    }
  }

  setRating(rating) {
    document.getElementById("rating-input").value = rating
    this.highlightStars(rating)

    // Update rating text
    const ratingText = document.getElementById("rating-text")
    if (ratingText) {
      ratingText.textContent = `(${rating}/5)`
    }
  }

  highlightStars(rating) {
    const stars = document.querySelectorAll(".rating-star")
    stars.forEach((star, index) => {
      if (index < rating) {
        star.classList.add("text-yellow-400", "fill-yellow-400")
        star.classList.remove("text-gray-300")
      } else {
        star.classList.remove("text-yellow-400", "fill-yellow-400")
        star.classList.add("text-gray-300")
      }
    })
  }

  showTestimonialModal() {
    const modal = document.getElementById("testimonial-modal")
    if (modal) {
      modal.classList.remove("hidden")
      document.body.style.overflow = "hidden"

      // Focus on first input
      const firstInput = modal.querySelector('input[type="text"]')
      if (firstInput) {
        setTimeout(() => firstInput.focus(), 100)
      }
    }
  }

  hideTestimonialModal() {
    const modal = document.getElementById("testimonial-modal")
    if (modal) {
      modal.classList.add("hidden")
      document.body.style.overflow = "auto"

      // Reset form
      this.resetForm()
    }
  }

  resetForm() {
    const form = document.getElementById("testimonial-form")
    if (form) {
      form.reset()
      this.setRating(5) // Default rating

      // Reset character counter
      const commentInput = document.getElementById("comment")
      const charCounter = document.getElementById("char-counter")
      if (commentInput && charCounter) {
        charCounter.textContent = `0/20`
      }
    }
  }

  async loadTestimonials() {
    try {
      const response = await fetch(`${this.apiUrl}?limit=6`)
      const data = await response.json()

      if (data.success) {
        this.displayTestimonials(data.data)
      }
    } catch (error) {
      console.error("Error loading testimonials:", error)
    }
  }

  displayTestimonials(testimonials) {
    const container = document.getElementById("testimonials-container")
    if (!container) return

    container.innerHTML = testimonials
      .map(
        (testimonial) => `
            <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-pink-400 rounded-full flex items-center justify-center text-white font-bold text-lg">
                        ${testimonial.name.charAt(0).toUpperCase()}
                    </div>
                    <div class="ml-4">
                        <h4 class="font-semibold text-gray-900">${testimonial.name}</h4>
                        <p class="text-sm text-gray-600">${testimonial.kos_name}</p>
                    </div>
                </div>
                <div class="flex items-center mb-3">
                    ${this.generateStars(testimonial.rating)}
                    <span class="ml-2 text-sm text-gray-500">${this.formatDate(testimonial.created_at)}</span>
                </div>
                <p class="text-gray-700 italic">"${testimonial.comment}"</p>
            </div>
        `,
      )
      .join("")
  }

  generateStars(rating) {
    let stars = ""
    for (let i = 1; i <= 5; i++) {
      if (i <= rating) {
        stars += '<i class="fas fa-star text-yellow-400"></i>'
      } else {
        stars += '<i class="fas fa-star text-gray-300"></i>'
      }
    }
    return stars
  }

  formatDate(dateString) {
    const date = new Date(dateString)
    const now = new Date()
    const diffTime = Math.abs(now - date)
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

    if (diffDays === 1) {
      return "1 hari lalu"
    } else if (diffDays < 7) {
      return `${diffDays} hari lalu`
    } else if (diffDays < 30) {
      const weeks = Math.floor(diffDays / 7)
      return `${weeks} minggu lalu`
    } else {
      const months = Math.floor(diffDays / 30)
      return `${months} bulan lalu`
    }
  }

  async submitTestimonial() {
    const form = document.getElementById("testimonial-form")
    const submitBtn = document.getElementById("submit-testimonial")
    const formData = new FormData(form)

    // Convert FormData to JSON
    const data = {}
    formData.forEach((value, key) => {
      data[key] = value
    })

    // Validate required fields
    if (!this.validateForm(data)) {
      return
    }

    // Show loading state
    submitBtn.disabled = true
    submitBtn.innerHTML = `
            <div class="flex items-center">
                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                Mengirim...
            </div>
        `

    try {
      const response = await fetch(this.apiUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
      })

      const result = await response.json()

      if (result.success) {
        this.showSuccessMessage("Terima kasih! Testimoni Anda telah dikirim dan akan ditinjau oleh tim kami.")
        this.hideTestimonialModal()

        // Reload testimonials
        setTimeout(() => {
          this.loadTestimonials()
        }, 1000)
      } else {
        this.showErrorMessage(result.message || "Gagal mengirim testimoni")
      }
    } catch (error) {
      console.error("Error submitting testimonial:", error)
      this.showErrorMessage("Terjadi kesalahan saat mengirim testimoni")
    } finally {
      // Reset button state
      submitBtn.disabled = false
      submitBtn.innerHTML = `
                <i class="fas fa-paper-plane mr-2"></i>
                Kirim Testimoni
            `
    }
  }

  validateForm(data) {
    const errors = []

    if (!data.name || data.name.trim().length < 2) {
      errors.push("Nama minimal 2 karakter")
    }

    if (!data.email || !this.isValidEmail(data.email)) {
      errors.push("Email tidak valid")
    }

    if (!data.kos || data.kos.trim().length < 3) {
      errors.push("Nama kos minimal 3 karakter")
    }

    if (!data.comment || data.comment.trim().length < 20) {
      errors.push("Testimoni minimal 20 karakter")
    }

    if (!data.rating || data.rating < 1 || data.rating > 5) {
      errors.push("Rating harus antara 1-5")
    }

    if (errors.length > 0) {
      this.showErrorMessage(errors.join(", "))
      return false
    }

    return true
  }

  isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    return emailRegex.test(email)
  }

  showSuccessMessage(message) {
    this.showNotification(message, "success")
  }

  showErrorMessage(message) {
    this.showNotification(message, "error")
  }

  showNotification(message, type) {
    // Create notification element
    const notification = document.createElement("div")
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm ${
      type === "success" ? "bg-green-500 text-white" : "bg-red-500 text-white"
    }`
    notification.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${type === "success" ? "check-circle" : "exclamation-circle"} mr-2"></i>
                <span>${message}</span>
                <button class="ml-4 text-white hover:text-gray-200" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `

    document.body.appendChild(notification)

    // Auto remove after 5 seconds
    setTimeout(() => {
      if (notification.parentElement) {
        notification.remove()
      }
    }, 5000)
  }
}

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  new TestimonialManager()
})

// Character counter for comment textarea
document.addEventListener("DOMContentLoaded", () => {
  const commentInput = document.getElementById("comment")
  const charCounter = document.getElementById("char-counter")

  if (commentInput && charCounter) {
    commentInput.addEventListener("input", () => {
      const length = commentInput.value.length
      charCounter.textContent = `${length}/20`

      if (length >= 20) {
        charCounter.classList.remove("text-red-500")
        charCounter.classList.add("text-green-500")
      } else {
        charCounter.classList.remove("text-green-500")
        charCounter.classList.add("text-red-500")
      }
    })
  }
})
