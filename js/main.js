
const root = document.documentElement;
const themeBtn = document.getElementById("theme-toggle");
let savedTheme = null;
try {
  savedTheme = localStorage.getItem("theme");
  if (savedTheme) root.setAttribute("data-theme", savedTheme);
} catch (e) {  }

themeBtn.addEventListener("click", () => {
  const next = root.getAttribute("data-theme") === "dark" ? "light" : "dark";
  root.setAttribute("data-theme", next);
  try { localStorage.setItem("theme", next); } catch (e) {}
});

const navToggle = document.getElementById("nav-toggle");
const navList = document.getElementById("nav-list");
navToggle.addEventListener("click", () => {
  const open = navList.classList.toggle("open");
  navToggle.setAttribute("aria-expanded", open);
});

const currentPage = location.pathname.split("/").pop() || "index.html";
document.querySelectorAll(".nav-list a").forEach(link => {
  if (link.getAttribute("href") === currentPage) link.classList.add("active");
});

const topBtn = document.getElementById("to-top");
window.addEventListener("scroll", () => {
  topBtn.classList.toggle("show", window.scrollY > 300);
});
topBtn.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" }));

const typingEl = document.getElementById("typing-text");
let typingWords = ["student", "web developer", "problem solver"];
if (typingEl) {
  let w = 0, c = 0, deleting = false;
  function type() {
    const word = typingWords[w] || typingWords[0] || "student";
    typingEl.textContent = word.slice(0, c);
    if (!deleting && c < word.length) c++;
    else if (!deleting) { deleting = true; setTimeout(type, 1200); return; }
    else if (c > 0) c--;
    else { deleting = false; w = (w + 1) % typingWords.length; }
    setTimeout(type, deleting ? 60 : 110);
  }
  type();
}

function animateRing(ring) {
  const target = Number(ring.dataset.level);
  const label = ring.querySelector("span");
  const start = performance.now();
  const duration = 1000;
  function step(now) {
    const progress = Math.min((now - start) / duration, 1);
    const value = Math.round(target * progress);
    ring.style.setProperty("--p", value);
    label.textContent = value + "%";
    if (progress < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

const ringObserver = "IntersectionObserver" in window ? new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      animateRing(entry.target);
      ringObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.4 }) : null;

function observeSkillRings(container = document) {
  container.querySelectorAll(".ring").forEach(ring => {
    if (ringObserver) ringObserver.observe(ring);
    else animateRing(ring);
  });
}
observeSkillRings();

document.querySelector(".skill-tabs")?.addEventListener("click", event => {
  const button = event.target.closest(".tab-btn");
  if (!button) return;
  document.querySelectorAll(".tab-btn").forEach(item => item.classList.toggle("active", item === button));
  document.querySelectorAll(".skill-card").forEach(card => {
    const show = button.dataset.tab === "all" || card.dataset.cat === button.dataset.tab;
    card.classList.toggle("hide", !show);
    if (show) animateRing(card.querySelector(".ring"));
  });
});

const filterBtns = document.querySelectorAll(".filter-btn");
filterBtns.forEach(btn => {
  btn.addEventListener("click", () => {
    filterBtns.forEach(b => b.classList.remove("active"));
    btn.classList.add("active");
    const filter = btn.dataset.filter;
    document.querySelectorAll(".project").forEach(card => {
      const show = filter === "all" || card.dataset.category === filter;
      card.classList.toggle("hide", !show);
    });
  });
});

const modal = document.getElementById("modal");
if (modal) {
  const modalImg = document.getElementById("modal-img");
  document.addEventListener("click", event => {
    const img = event.target.closest(".project-img");
    if (!img) return;
    modalImg.src = img.src;
    modalImg.alt = img.alt;
    modal.hidden = false;
  });
  const closeModal = () => { modal.hidden = true; };
  document.getElementById("modal-close").addEventListener("click", closeModal);
  modal.addEventListener("click", e => { if (e.target === modal) closeModal(); });
  document.addEventListener("keydown", e => { if (e.key === "Escape") closeModal(); });
}

document.querySelectorAll(".acc-btn").forEach(btn => {
  btn.addEventListener("click", () => {
    const panel = btn.nextElementSibling;
    const isOpen = btn.getAttribute("aria-expanded") === "true";
    btn.setAttribute("aria-expanded", !isOpen);
    panel.style.maxHeight = isOpen ? "0" : panel.scrollHeight + "px";
  });
});

const form = document.getElementById("contact-form");
if (form) {
  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

  function showError(id, message) {
    document.getElementById(id + "-error").textContent = message;
    document.getElementById(id).classList.toggle("invalid", message !== "");
  }

  form.addEventListener("submit", async e => {
    e.preventDefault();
    const name = form.elements.namedItem("name").value.trim();
    const email = form.elements.namedItem("email").value.trim();
    const message = form.elements.namedItem("message").value.trim();
    let valid = true;

    if (name.length < 2) { showError("name", "Please enter your name."); valid = false; }
    else showError("name", "");

    if (!emailPattern.test(email)) { showError("email", "Please enter a valid email address."); valid = false; }
    else showError("email", "");

    if (message.length < 10) { showError("message", "Message must be at least 10 characters."); valid = false; }
    else showError("message", "");

    const success = document.getElementById("form-success");
    const serverError = document.getElementById("form-server-error");
    success.hidden = true;
    serverError.hidden = true;
    success.hidden = !valid;
    if (valid) {
      const submitButton = form.querySelector('button[type="submit"]');
      submitButton.disabled = true;
      try {
        const tokenResponse = await fetch("submit_message.php?action=token", { credentials: "same-origin" });
        const tokenData = await tokenResponse.json();
        if (!tokenResponse.ok || !tokenData.csrf_token) {
          throw new Error(tokenData.message || "The contact form could not be prepared. Please try again.");
        }

        document.getElementById("contact-csrf-token").value = tokenData.csrf_token;
        const response = await fetch(form.action, {
          method: "POST",
          body: new FormData(form),
          credentials: "same-origin"
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || "Your message could not be sent.");

        success.textContent = result.message;
        success.hidden = false;
        form.reset();
      } catch (error) {
        serverError.textContent = error.message || "Your message could not be sent. Please try again later.";
        serverError.hidden = false;
      } finally {
        submitButton.disabled = false;
      }
    }
  });
}

function appendText(parent, tag, value, className) {
  const element = document.createElement(tag);
  if (className) element.className = className;
  element.textContent = value == null ? "" : String(value);
  parent.appendChild(element);
  return element;
}

function formatYearRange(start, end) {
  const startText = start ? String(start).slice(0, 4) : "";
  const endText = end ? String(end).slice(0, 4) : (startText ? "Present" : "");
  return startText && endText ? `${startText} – ${endText}` : (startText || endText);
}

function formatMonthYear(value) {
  if (!value) return "";
  const date = new Date(`${value}T12:00:00`);
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString(undefined, { month: "short", year: "numeric" });
}

function renderEducation(items, parent) {
  parent.replaceChildren();
  items.forEach(item => {
    const p = document.createElement("p");
    if (item.course) {
      const strong = appendText(p, "strong", item.course);
      strong.appendChild(document.createTextNode(" — "));
    }
    p.appendChild(document.createTextNode(item.school || ""));
    if (item.start_year || item.end_year) {
      p.appendChild(document.createElement("br"));
      p.appendChild(document.createTextNode(formatYearRange(item.start_year, item.end_year)));
    }
    if (item.description) {
      p.appendChild(document.createElement("br"));
      p.appendChild(document.createTextNode(item.description));
    }
    parent.appendChild(p);
  });
}

function renderExperience(items, parent) {
  parent.replaceChildren();
  items.forEach(item => {
    const p = document.createElement("p");
    const strong = appendText(p, "strong", item.position || "");
    if (item.company) {
      strong.appendChild(document.createTextNode(" — "));
      p.appendChild(document.createTextNode(item.company));
    }
    const dates = [formatMonthYear(item.start_date), formatMonthYear(item.end_date || "")].filter(Boolean);
    if (dates.length) p.appendChild(document.createTextNode(`, ${dates.join(" – ")}${item.end_date ? "" : " – Present"}`));
    if (item.description) {
      p.appendChild(document.createElement("br"));
      p.appendChild(document.createTextNode(item.description));
    }
    parent.appendChild(p);
  });
}

function renderAchievements(items, parent) {
  parent.replaceChildren();
  items.forEach(item => {
    const p = document.createElement("p");
    const strong = appendText(p, "strong", item.title || "");
    if (item.organization) {
      strong.appendChild(document.createTextNode(" — "));
      p.appendChild(document.createTextNode(item.organization));
    }
    if (item.date_received) p.appendChild(document.createTextNode(`, ${formatMonthYear(item.date_received)}`));
    if (item.description) {
      p.appendChild(document.createElement("br"));
      p.appendChild(document.createTextNode(item.description));
    }
    if (item.image_url) {
      const image = document.createElement("img");
      image.src = item.image_url;
      image.alt = `Certificate for ${item.title || "achievement"}`;
      image.loading = "lazy";
      p.appendChild(document.createElement("br"));
      p.appendChild(image);
    }
    parent.appendChild(p);
  });
}

fetch("public_content.php", { headers: { Accept: "application/json" }, credentials: "same-origin" })
  .then(response => response.ok ? response.json() : Promise.reject(new Error("Portfolio content unavailable")))
  .then(data => {
    const settings = data.settings || {};
    const profile = data.profile || {};
    const logoText = settings.logo_text;
    if (logoText) document.querySelectorAll("[data-site-logo]").forEach(element => { element.textContent = logoText; });
    if (settings.site_title) {
      const pageName = document.title.split("|")[0].trim();
      document.title = `${pageName} | ${settings.site_title}`;
    }
    if (settings.footer_text) {
      document.querySelectorAll("[data-footer-text]").forEach(element => {
        element.textContent = `© ${new Date().getFullYear()} ${settings.footer_text}`;
      });
    }
    if (settings.default_theme && !savedTheme) root.setAttribute("data-theme", settings.default_theme);
    if (/^#[0-9a-f]{6}$/i.test(settings.accent_color || "")) {
      root.style.setProperty("--accent", settings.accent_color);
    }

    if (profile.full_name) document.querySelectorAll("[data-profile-name]").forEach(element => { element.textContent = profile.full_name; });
    if (profile.introduction) document.querySelectorAll(".hero .lead").forEach(element => { element.textContent = profile.introduction; });
    if (profile.biography) document.querySelectorAll("[data-profile-biography]").forEach(element => { element.textContent = profile.biography; });
    if (profile.title) typingWords = profile.title.split(",").map(word => word.trim()).filter(Boolean);
    if (profile.profile_image) {
      document.querySelectorAll("[data-profile-image]").forEach(element => {
        element.src = profile.profile_image;
        if (profile.full_name) element.alt = `Portrait of ${profile.full_name}`;
      });
    }
    if (profile.email) {
      document.querySelectorAll("[data-profile-email]").forEach(element => {
        element.href = `mailto:${profile.email}`;
        element.textContent = profile.email;
      });
    }

    const projectGrid = document.getElementById("public-project-grid");
    if (projectGrid && data.projects.length) {
      projectGrid.replaceChildren();
      const projectFilters = projectGrid.closest("section").querySelector(".filters");
      projectFilters.hidden = !data.projects_have_categories;
      if (projectFilters.hidden) projectFilters.style.display = "none";
      data.projects.forEach(project => {
        const card = document.createElement("article");
        card.className = "card project";
        card.dataset.category = ["web", "python", "data"].includes(project.category) ? project.category : "";
        if (project.image_url) {
          const image = document.createElement("img");
          image.src = project.image_url;
          image.alt = `Screenshot of ${project.title || "project"}`;
          image.className = "project-img";
          image.loading = "lazy";
          card.appendChild(image);
        }
        const body = document.createElement("div");
        body.className = "project-body";
        appendText(body, "h3", project.title);
        appendText(body, "p", project.description);
        if (project.tech_stack) {
          const tech = document.createElement("p");
          tech.className = "tech";
          appendText(tech, "strong", "Tech: ");
          tech.appendChild(document.createTextNode(project.tech_stack));
          body.appendChild(tech);
        }
        if (project.project_url) {
          try {
            const url = new URL(project.project_url);
            if (["http:", "https:"].includes(url.protocol)) {
              const link = appendText(body, "a", "View project");
              link.href = url.href;
              link.target = "_blank";
              link.rel = "noopener noreferrer";
            }
          } catch (error) { }
        }
        card.appendChild(body);
        projectGrid.appendChild(card);
      });
    }

    const skillsGrid = document.getElementById("public-skills-grid");
    if (skillsGrid && data.skills.length) {
      skillsGrid.replaceChildren();
      data.skills.forEach(skill => {
        const card = document.createElement("article");
        card.className = "skill-card";
        card.dataset.cat = skill.category;
        const ring = document.createElement("div");
        ring.className = "ring";
        ring.dataset.level = String(skill.level);
        appendText(ring, "span", "0%");
        card.appendChild(ring);
        appendText(card, "h3", skill.name);
        skillsGrid.appendChild(card);
      });
      observeSkillRings(skillsGrid);
    }

    if (data.education.length) {
      const aboutEducation = document.getElementById("public-about-education");
      if (aboutEducation) renderEducation(data.education, aboutEducation);
      const resumeEducation = document.getElementById("public-resume-education");
      if (resumeEducation) renderEducation(data.education, resumeEducation);
    }
    if (data.experience.length) {
      const resumeExperience = document.getElementById("public-resume-experience");
      if (resumeExperience) renderExperience(data.experience, resumeExperience);
    }
    if (data.achievements.length) {
      const resumeAchievements = document.getElementById("public-resume-achievements");
      if (resumeAchievements) renderAchievements(data.achievements, resumeAchievements);
    }
    const interests = document.getElementById("public-interests");
    if (interests && data.interests.length) {
      interests.replaceChildren();
      data.interests.forEach(item => appendText(interests, "li", item));
    }
  })
  .catch(() => { /* Static starter content remains visible if the PHP service is unavailable. */ });
