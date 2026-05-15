// LOGIN AND REGISTER MODAL
function openLoginModal() {
    document.getElementById("loginModalDisplay").style.display = "flex";
    document.getElementById("registerModalDisplay").style.display = "none";
}

function openRegisterModal() {
    document.getElementById("registerModalDisplay").style.display = "flex";
    document.getElementById("loginModalDisplay").style.display = "none";
}

// close when clicking outside
window.addEventListener("click", function(e) {
    const modal = document.getElementById("loginModalDisplay");
    if (e.target === modal) {
        modal.style.display = "none";
    }
});

window.addEventListener("click", function(e) {
    const modal = document.getElementById("registerModalDisplay");
    if (e.target === modal) {
        modal.style.display = "none";
    }
});

window.addEventListener("click", function(e) {
    const modal = document.getElementById("accountModalDisplay");
    if (e.target === modal) {
        modal.style.display = "none";
    }
});





// PRODUCT DESCRIPTION TABS
function openTab(evt, tabName) {
  const panels = document.getElementsByClassName("tab-panel");
  for (let i = 0; i < panels.length; i++) {
    panels[i].style.display = "none";
  }

  const buttons = document.getElementsByClassName("pd-header-btn");
  for (let i = 0; i < buttons.length; i++) {
    buttons[i].classList.remove("active");
  }

  document.getElementById(tabName).style.display = "block";
  evt.currentTarget.classList.add("active");
}


// QUANTITY BUTTON (product details page)
function changeQty(toAdd) {
  const qtyInput = document.getElementById("qty");
  if (!qtyInput) return;

  let currentVal = parseInt(qtyInput.textContent);
  let newVal     = Math.max(1, currentVal + toAdd);
  qtyInput.textContent = newVal;

  const buynowQty = document.getElementById("buynow-qty");
  if (buynowQty) buynowQty.value = newVal;
}

var selectedIds = [];

        // Add click listener to every cart card
        var cards = document.querySelectorAll(".cart-card");

        cards.forEach(function (card) {

            card.addEventListener("click", function (e) {
            

                // If user clicked the Remove link — ignore, let it navigate
                if (e.target.closest("a")) {
                    return;
                }

                var stockStatus = card.getAttribute("data-stock");

                if (stockStatus === "Out of Stock") {
                    alert("This item is currently out of stock.");
                    return;
                }
                var itemId = parseInt(card.getAttribute("data-item-id"));
                var index = selectedIds.indexOf(itemId);

                if (index === -1) {
                    // Not selected — select it
                    selectedIds.push(itemId);
                    card.style.border = "2px solid #0049af";
                    card.style.backgroundColor = "#f0f5ff";
                } else {
                    // Already selected — deselect it
                    selectedIds.splice(index, 1);
                    card.style.border = "2px solid transparent";
                    card.style.backgroundColor = "white";
                }

                updateSummary();
            });
        });


        function updateSummary() {
            var total = 0;
            var itemCount = selectedIds.length;

            selectedIds.forEach(function (itemId) {
                var card = document.querySelector(".cart-card[data-item-id='" + itemId + "']");
                var price = parseFloat(card.getAttribute("data-price"));
                var qty = parseInt(card.getAttribute("data-qty"));
                total += price * qty;
            });

            document.getElementById("subtotal").textContent = "₱" + total.toLocaleString("en-PH", { minimumFractionDigits: 2 });
            document.getElementById("cart-total").textContent = "₱" + total.toLocaleString("en-PH", { minimumFractionDigits: 2 });
            document.getElementById("summary-count").textContent = itemCount + (itemCount === 1 ? " item selected" : " items selected");
        }


        function proceedToCheckout() {
            if (selectedIds.length === 0) {
                alert("Please select at least one item to checkout.");
                return;
            }
            window.location.href = "checkout.php?items=" + selectedIds.join(",");
        }



// CALENDAR AND BOOKING
const monthYearEl   = document.getElementById("monthYear");
const daysContainer = document.getElementById("days");
const prevBtn       = document.getElementById("prev");
const nextBtn       = document.getElementById("next");

if (monthYearEl && daysContainer && prevBtn && nextBtn) {

  // ---- SETUP ----
  let currentDate       = new Date();
  let selectedDateValue = "";

  const months = [
    "January", "February", "March",    "April",
    "May",     "June",     "July",     "August",
    "September","October", "November", "December"
  ];

  // All available time slots
  // Must match exactly what is in your time buttons
  const allTimes = [
    "9:00 AM", "10:00 AM", "11:00 AM",
    "1:00 PM", "2:00 PM",  "3:00 PM", "4:00 PM"
  ];


  // ---- SELECT DATE ----
  // Called when user clicks a day on the calendar
  function selectDate(dateString, displayText) {
    document.getElementById("selected-date").value = dateString;
    document.getElementById("selected-date-label").textContent = "Selected: " + displayText;
    selectedDateValue = dateString;
  }


  // ---- UPDATE TIME SLOTS ----
  // Called every time a date is clicked
  // Checks which times are already booked for that date
  // and disables those buttons
  function updateTimeSlots(selectedDate) {
    const allBtns = document.querySelectorAll(".time-btn");

    for (let i = 0; i < allBtns.length; i++) {
      const btn  = allBtns[i];
      const time = btn.getAttribute("data-time");

      // Reset button first — remove all special styles
      btn.classList.remove("time-booked", "time-selected");
      btn.disabled = false;

      // bookedSlots comes from PHP via services-details.php
      // Check if this time is already taken for the selected date
      if (bookedSlots[selectedDate] && bookedSlots[selectedDate].includes(time)) {
        btn.classList.add("time-booked");
        btn.disabled = true;
      }
    }
  }


  // ---- SELECT TIME ----
  // Called when user clicks a time button
  function selectTime(btn, time) {
    // If the button is disabled (already booked), do nothing
    if (btn.disabled) return;

    // Remove selected style from all buttons
    const allBtns = document.querySelectorAll(".time-btn");
    for (let i = 0; i < allBtns.length; i++) {
      allBtns[i].classList.remove("time-selected");
    }

    // Highlight the clicked one
    btn.classList.add("time-selected");

    // Save into the hidden input so PHP can read it on submit
    document.getElementById("selected-time").value             = time;
    document.getElementById("selected-time-label").textContent = "Selected: " + time;
  }


  // ---- RENDER CALENDAR ----
  // Builds the calendar grid for the current month
  function renderCalendar() {
    daysContainer.innerHTML = "";

    const year  = currentDate.getFullYear();
    const month = currentDate.getMonth();

    monthYearEl.textContent = months[month] + " " + year;

    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate()); // no time component
    const firstDayIndex = new Date(year, month, 1).getDay();
    const lastDay = new Date(year, month + 1, 0).getDate();

    // Add empty boxes before the first day of the month
    for (let i = 0; i < firstDayIndex; i++) {
      const blank = document.createElement("div");
      daysContainer.appendChild(blank);
    }

    // Build one box per day
    for (let d = 1; d <= lastDay; d++) {

      const m           = String(month + 1).padStart(2, "0");
      const dd          = String(d).padStart(2, "0");
      const fullDate    = year + "-" + m + "-" + dd;
      const displayText = months[month] + " " + d + ", " + year;

      const dayDiv = document.createElement("div");
      dayDiv.classList.add("day");
      dayDiv.textContent = d;

      // Highlight today's date
      const thisDate = new Date(year, month, d);
      if (thisDate.getTime() === today.getTime()) {
        dayDiv.classList.add("today");
      }


      // Gray out past days AND today — user can only book future dates
      const thisDay = new Date(year, month, d);

      if (thisDay <= today) {
        dayDiv.classList.add("past-day");
        daysContainer.appendChild(dayDiv);
      continue;
      }

      // Mark as fully booked if all time slots are taken
      if (
        bookedSlots[fullDate] &&
        bookedSlots[fullDate].length >= allTimes.length
      ) {
        dayDiv.classList.add("fully-booked");
        dayDiv.title = "Fully booked";
        daysContainer.appendChild(dayDiv);
        continue;   // skip to next day — no click event needed
      }

      // Highlight if user already selected this day
      if (fullDate === selectedDateValue) {
        dayDiv.classList.add("selected-day");
      }

      // Click event — what happens when user clicks a day
      (function(fd, dt) {
        dayDiv.addEventListener("click", function() {

          // Remove selected highlight from all days
          const allDays = document.querySelectorAll(".day");
          for (let i = 0; i < allDays.length; i++) {
            allDays[i].classList.remove("selected-day");
          }

          // Highlight this day
          dayDiv.classList.add("selected-day");

          // Save the selected date
          selectDate(fd, dt);

          // Update which time buttons are available for this date
          updateTimeSlots(fd);
        });
      })(fullDate, displayText);

      daysContainer.appendChild(dayDiv);
    }
  }


  // ---- PREV / NEXT MONTH BUTTONS ----
  prevBtn.addEventListener("click", function() {
    currentDate.setMonth(currentDate.getMonth() - 1);
    renderCalendar();
  });

  nextBtn.addEventListener("click", function() {
    currentDate.setMonth(currentDate.getMonth() + 1);
    renderCalendar();
  });


  // ---- START THE CALENDAR ON PAGE LOAD ----
  renderCalendar();

} // end of calendar if block

// ADD TO CART AND WISHLIST POPUP MESSAGE
window.addEventListener("load", function () {
    const params = new URLSearchParams(window.location.search);

    if (params.get("added") === "1") {
        showToast("Added to cart!");
    }

    const wishlistAction = params.get("wishlisted");
    if (wishlistAction === "added") {
        showToast("Added to wishlist!");
    } else if (wishlistAction === "removed") {
    showToast("Removed from wishlist!");
}
});

function showToast(message) {
    const toast = document.getElementById("atc-toast");
    if (toast) {
        toast.textContent = message;
        toast.classList.add("show");
        setTimeout(() => toast.classList.remove("show"), 2500);
    }
}

// Sync quantity before form submit
const atcForm = document.getElementById("atc-form");
if (atcForm) {
    atcForm.addEventListener("submit", function () {
        document.getElementById("atc-qty").value =
            document.getElementById("qty").textContent;
    });
}

// CART — Select/Deselect + Order Summary + Persist Selection
window.addEventListener("load", function () {

    const cartCards = document.querySelectorAll(".cart-card");
    const subtotalEl = document.getElementById("subtotal");
    const cartTotalEl = document.getElementById("cart-total");
    const summaryCountEl = document.getElementById("summary-count");

    if (cartCards.length === 0) return;

    // Kunin yung saved selections mula sa localStorage
    let savedSelections = [];
    localStorage.removeItem("cartSelections");

    // I-restore yung selected state ng bawat card
    cartCards.forEach(function (card) {
        const cardId = card.getAttribute("data-id");

        if (savedSelections.includes(cardId)) {
            card.classList.add("selected");
        }

        // Click event
        card.addEventListener("click", function (e) {

            // Ignore kung delete o quantity button ang na-click
            if (e.target.closest(".cart-delete")) return;
            if (e.target.closest(".quantity-controls")) return;

            card.classList.toggle("selected");

            // I-save yung bagong selections sa localStorage
            saveSelections();

            // I-update yung order summary
            updateSummary();
        });
    });

    // I-update agad yung summary base sa restored selections
    updateSummary();

    function saveSelections() {
        const selected = [];
        document.querySelectorAll(".cart-card.selected").forEach(function (card) {
            selected.push(card.getAttribute("data-id"));
        });
        localStorage.setItem("cartSelections", JSON.stringify(selected));
    }

    function updateSummary() {
    const selectedCards = document.querySelectorAll(".cart-card.selected");

    let total = 0;
    let itemCount = 0;

    selectedCards.forEach(function (card) {

        const stockStatus = card.getAttribute("data-stock");

        // SKIP OUT OF STOCK ITEMS
        if (stockStatus === "Out of Stock") {
            card.classList.remove("selected");
            return;
        }

        const price = parseFloat(card.getAttribute("data-price"));
        const qty = parseInt(card.getAttribute("data-qty"));

        total += price * qty;
        itemCount += qty;
    });

    document.getElementById("subtotal").textContent =
        "₱" + total.toLocaleString("en-PH", { minimumFractionDigits: 2 });

    document.getElementById("cart-total").textContent =
        "₱" + total.toLocaleString("en-PH", { minimumFractionDigits: 2 });

    document.getElementById("summary-count").textContent =
        itemCount + " item" + (itemCount !== 1 ? "s" : "") + " selected";
}
});

// PASSWORD STRENGTH KINEME
const passwordInput = document.querySelector("#reg-pass");

const hints = document.querySelector(".password-hints");
const strengthText = document.querySelector(".strength-text");

const len = document.querySelector("#len");
const upper = document.querySelector("#upper");
const num = document.querySelector("#num");
const sym = document.querySelector("#sym");

if (passwordInput) {
  passwordInput.addEventListener("input", function () {

    let value = passwordInput.value;

    // SHOW / HIDE WITH ANIMATION
    if (value.length > 0) {
      hints.classList.add("show");
    } else {
      hints.classList.remove("show");
      strengthText.textContent = "";
      return;
    }

    // RULE CHECKER
    let score = 0;

    score += toggleRule(len, value.length >= 8);
    score += toggleRule(upper, /[A-Z]/.test(value));
    score += toggleRule(num, /[0-9]/.test(value));
    score += toggleRule(sym, /[^A-Za-z0-9]/.test(value));

    // SUMMARY TEXT
    if (score <= 1) {
      strengthText.textContent = "Weak password. Try adding letter, number, and symbol.";
      strengthText.style.color = "red";
    } 
    else if (score === 2) {
      strengthText.textContent = "Medium password. Improve it for better security.";
      strengthText.style.color = "orange";
    } 
    else if (score === 3) {
      strengthText.textContent = "Strong password. Almost there!";
      strengthText.style.color = "green";
    } 
    else {
      strengthText.textContent = "Very strong password. Good job!";
      strengthText.style.color = "darkgreen";
    }

  });
}

// helper function
function toggleRule(element, isValid) {
  if (!element) return 0;

  if (isValid) {
    element.classList.add("valid");
    element.classList.remove("invalid");
    return 1;
  } else {
    element.classList.add("invalid");
    element.classList.remove("valid");
    return 0;
  }
}

// LOGIN AND REGISTER

document.querySelectorAll(".login-form, .reg-form").forEach(form => {

  form.addEventListener("submit", function (e) {
    e.preventDefault();

    let errorDisplay = form.querySelector(".error-message");

    let username = form.querySelector("[name='username']");
    let email = form.querySelector("[name='email']");
    let password = form.querySelector("[name='password']");
    let confirm = form.querySelector("[name='confirm_password']");

    let clientErrors = [];

    // RESET STYLES
    form.querySelectorAll("input").forEach(input => {
      input.classList.remove("input-error", "input-success");

      let label = form.querySelector(`label[for="${input.id}"]`);
      if (label) label.classList.remove("label-error", "label-success");
    });

    // ======================
    // FUNCTIONS
    // ======================
    function setError(input) {
      if (!input) return;

      let label = form.querySelector(`label[for="${input.id}"]`);

      input.classList.add("input-error");
      input.classList.remove("input-success");

      if (label) {
        label.classList.add("label-error");
        label.classList.remove("label-success");
      }
    }

    function setSuccess(input) {
      if (!input) return;

      let label = form.querySelector(`label[for="${input.id}"]`);

      input.classList.add("input-success");
      input.classList.remove("input-error");

      if (label) {
        label.classList.add("label-success");
        label.classList.remove("label-error");
      }
    }

    // ======================
    // CLIENT VALIDATION (DETAILED)
    // ======================

    // USERNAME
    if (username) {
      if (username.value.trim() === "") {
        setError(username);
        clientErrors.push("Username is required.");
      } else {
        setSuccess(username);
      }
    }

    // EMAIL (REGISTER ONLY)
    if (email) {
      if (email.value.trim() === "") {
        setError(email);
        clientErrors.push("Email is required.");
      } else {
        setSuccess(email);
      }
    }

    // PASSWORD
    if (password) {
      if (password.value.trim() === "") {
        setError(password);
        clientErrors.push("Password is required.");
      } else if (password.value.length < 8) {
        setError(password);
        clientErrors.push("Password must be at least 8 characters.");
      } else {
        setSuccess(password);
      }
    }

    // CONFIRM PASSWORD
    if (confirm) {
      if (confirm.value.trim() === "") {
        setError(confirm);
        clientErrors.push("Confirm your password.");
      } else if (password && confirm.value !== password.value) {
        setError(confirm);
        clientErrors.push("Password does not match.");
      } else {
        setSuccess(confirm);
      }
    }

    // ======================
    // STOP IF CLIENT ERROR
    // ======================
    if (clientErrors.length > 0) {
      if (errorDisplay) errorDisplay.textContent = clientErrors[0];
      return;
    }

    // ======================
    // AJAX REQUEST (SERVER SIDE)
    // ======================
  let formData = new FormData(form);

fetch(form.action, {
  method: "POST",
  body: formData
})
.then(res => res.text())
.then(data => {
  data = data.trim();

  // CLEAR OLD MESSAGE
  if (errorDisplay) errorDisplay.textContent = "";

  // ======================
  // SUCCESS ROUTING
  // ======================

  if (data === "login") {
    const regModal = document.getElementById("registerModalDisplay");
    if (regModal) {
        regModal.style.display = "none";
        openLoginModal();
    }
    return;
  }

  if (data === "account-info") {
    // Close all modals
    document.getElementById("loginModalDisplay").style.display = "none";
    document.getElementById("registerModalDisplay").style.display = "none";
    // Redirect to the new setup page instead of showing the old modal
    window.location.href = "account-info.php";
    return;
}

  if (data === "account-info-address") {
    document.getElementById("loginModalDisplay").style.display = "none";
    document.getElementById("registerModalDisplay").style.display = "none";
    window.location.href = "account-info.php?step=address";
    return;
}

  if (data === "success") {
    window.location.href = "account-info.php";
    return;
}

  if (data === "index") {
    document.querySelectorAll('.login-modal, .register-modal, .account-modal').forEach(modal => {
        modal.style.display = "none";
    });
    window.location.href = "index.php";
    return;
}

  if (data === "admin") {
    window.location.href = "admin-dashboard.php";
    return;
  }

  // ERROR HANDLING
  else {
    if (errorDisplay) errorDisplay.textContent = data;
    
    // Account modal error
    const profileError = document.getElementById('profile-error');
    if (profileError) profileError.textContent = data;
  }
});

});

});


// LIVE SEARCH SUGGESTIONS
function initSearch(inputId, suggestionsId) {
    console.log(`🔍 Initializing: ${inputId} -> ${suggestionsId}`);
    
    const searchInput = document.getElementById(inputId);
    const suggestionsBox = document.getElementById(suggestionsId);

    if (!searchInput || !suggestionsBox) {
        console.error(" Elements not found");
        return;
    }

    let searchTimeout = null;

    searchInput.addEventListener("input", function () {
        const q = this.value.trim();
        console.log("⌨Typing:", q);

        clearTimeout(searchTimeout);
        suggestionsBox.innerHTML = "";
        suggestionsBox.style.display = "none";
        suggestionsBox.classList.remove('showing');

        if (q.length < 2) return;

        searchTimeout = setTimeout(function () {
            console.log("Fetching:", q);
            
            fetch("search-ajax.php?q=" + encodeURIComponent(q))
                .then(res => {
                    if (!res.ok) throw new Error('Network error: ' + res.status);
                    return res.json();
                })
                .then(data => {
                    console.log("Data:", data);
                    suggestionsBox.innerHTML = "";

                    if (!data || data.length === 0) {
                        suggestionsBox.style.display = "none";
                        return;
                    }

                    // ADD ALL SUGGESTIONS FIRST
                    data.forEach(function (item) {
                        const div = document.createElement("div");
                        div.className = "suggestion-item";
                        div.innerHTML = `
                            <img src="${item.image}" alt="${item.name}" onerror="this.src='assets/no-image.png'" />
                            <div class="suggestion-info">
                                <span class="suggestion-name">${item.name}</span>
                                <span class="suggestion-cat">${item.category}</span>
                            </div>
                            <span class="suggestion-price">₱${item.price}</span>
                        `;
                        div.addEventListener("click", function () {
                            window.location.href = "product-details.php?id=" + item.id;
                        });
                        suggestionsBox.appendChild(div);
                    });

                    // ADD "SEE ALL" LAST
                    const seeAll = document.createElement("div");
                    seeAll.className = "suggestion-see-all";
                    seeAll.textContent = `See all results for "${q}"`;
                    seeAll.addEventListener("click", function () {
                        window.location.href = "search.php?q=" + encodeURIComponent(q);
                    });
                    suggestionsBox.appendChild(seeAll);

                    // POSITION CORRECTLY
                    const rect = searchInput.getBoundingClientRect();
                    suggestionsBox.style.top = (rect.bottom + window.scrollY + 4) + 'px';
                    suggestionsBox.style.left = (rect.left + window.scrollX) + 'px';
                    suggestionsBox.style.width = rect.width + 'px';

                    // SHOW IT
                    suggestionsBox.style.display = "block";
                    suggestionsBox.classList.add('showing');
                    console.log(" SHOWING suggestions at:", suggestionsBox.style.top, suggestionsBox.style.left);
                })
                .catch(error => {
                    console.error('Error:', error);
                    suggestionsBox.style.display = "none";
                });
        }, 300);
    });

    // Close when clicking outside
    document.addEventListener("click", function (e) {
        if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
            suggestionsBox.style.display = "none";
            suggestionsBox.classList.remove('showing');
        }
    });

    searchInput.addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
            suggestionsBox.style.display = "none";
            suggestionsBox.classList.remove('showing');
        }
    });
}

// Initialize
document.addEventListener("DOMContentLoaded", function() {
    console.log("🚀 DOM ready");
    
    // Homepage
    if (document.getElementById("searchInput")) {
        initSearch("searchInput", "searchSuggestions");
    }
    
    // Search page
    if (document.getElementById("searchInputSearch")) {
        initSearch("searchInputSearch", "searchSuggestionsSearch");
    }
});
