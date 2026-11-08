"use strict";

/* ============================================================================
   Public tracking detail pages (track.php / track_online_shopping.php).

   Reveal-on-scroll, count-up numbers, "copy tracking number", and the fold
   buttons that hide the earlier stages of a long journey / history. Vanilla —
   these pages do not load jQuery. Everything here is progressive: without
   JS every stage and update is visible and nothing animates.

   No map: the route map and its Leaflet/Nominatim dependency were removed, so
   nothing here talks to a third party any more.
   ========================================================================== */

(function () {
  // Reveal-hiding is gated on this class so a JS failure can never leave the
  // page blank.
  document.documentElement.classList.add("trk-js");

  var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  document.addEventListener("DOMContentLoaded", function () {
    initFolds();
    initReveal();
    initCountUps();
    initCopy();
  });

  /* ── Fold earlier stages / updates ──────────────────────────────────────── */
  function initFolds() {
    var buttons = document.querySelectorAll(".trk-fold__btn[data-fold-for], .trk-fold[data-fold-for] .trk-fold__btn");
    buttons.forEach(function (btn) {
      var holder = btn.hasAttribute("data-fold-for") ? btn : btn.closest("[data-fold-for]");
      var list = holder && document.getElementById(holder.getAttribute("data-fold-for"));
      if (!list) return;
      var early = list.querySelectorAll(".trk-stage--early, .trk-tl--early");
      if (!early.length) return;

      list.classList.add("is-folded");
      btn.removeAttribute("hidden");
      btn.setAttribute("aria-expanded", "false");

      btn.addEventListener("click", function () {
        var open = list.classList.toggle("is-folded") === false;
        btn.setAttribute("aria-expanded", open ? "true" : "false");
        if (open) {
          // Move focus to the first revealed item so keyboard and screen
          // reader users land on what just appeared.
          var first = early[0];
          first.setAttribute("tabindex", "-1");
          first.focus({ preventScroll: false });
        }
      });
    });
  }

  /* ── Reveal on scroll ───────────────────────────────────────────────────── */
  function initReveal() {
    var els = document.querySelectorAll("[data-reveal]");
    if (!els.length) return;

    if (reduceMotion || !("IntersectionObserver" in window)) {
      for (var i = 0; i < els.length; i++) els[i].classList.add("is-in");
      return;
    }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("is-in");
        io.unobserve(entry.target);
      });
    }, { threshold: 0.08, rootMargin: "0px 0px -40px 0px" });

    els.forEach(function (el) { io.observe(el); });

    // Belt and braces: anything still hidden after 2s (throttled rAF in a
    // background tab, a headless preview pane) is shown anyway.
    setTimeout(function () {
      document.querySelectorAll("[data-reveal]:not(.is-in)").forEach(function (el) {
        var r = el.getBoundingClientRect();
        if (r.top < window.innerHeight) el.classList.add("is-in");
      });
    }, 2000);
  }

  /* ── Count-ups ──────────────────────────────────────────────────────────── */
  function initCountUps() {
    var els = document.querySelectorAll("[data-countup]");

    els.forEach(function (el) {
      var target = parseFloat(el.getAttribute("data-countup"));
      if (isNaN(target)) { el.textContent = el.getAttribute("data-countup"); return; }

      var decimals = (String(target).split(".")[1] || "").length;
      if (reduceMotion) { el.textContent = decimals ? target.toFixed(decimals) : String(target); return; }
      var start = null;
      var duration = 900;

      function frame(ts) {
        if (start === null) start = ts;
        var p = Math.min(1, (ts - start) / duration);
        // ease-out cubic
        var v = target * (1 - Math.pow(1 - p, 3));
        el.textContent = decimals ? v.toFixed(decimals) : Math.round(v).toString();
        if (p < 1) requestAnimationFrame(frame);
        else el.textContent = decimals ? target.toFixed(decimals) : String(target);
      }

      requestAnimationFrame(frame);
    });
  }

  /* ── Copy tracking number ───────────────────────────────────────────────── */
  function initCopy() {
    var btn = document.querySelector("[data-copy]");
    if (!btn) return;

    var label = btn.querySelector("[data-copy-label]");
    var status = document.querySelector("[data-copy-status]");
    var icon = btn.querySelector("i");
    var timer = null;

    btn.addEventListener("click", function () {
      var text = btn.getAttribute("data-copy") || "";
      var done = function () {
        if (label) label.textContent = "Copied";
        if (icon) icon.className = "mdi mdi-check";
        btn.classList.add("is-copied");
        if (status) status.textContent = "Tracking number " + text + " copied to clipboard";
        clearTimeout(timer);
        timer = setTimeout(function () {
          if (label) label.textContent = "Copy";
          if (icon) icon.className = "mdi mdi-content-copy";
          btn.classList.remove("is-copied");
          if (status) status.textContent = "";
        }, 1800);
      };

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, fallback);
      } else {
        fallback();
      }

      function fallback() {
        var ta = document.createElement("textarea");
        ta.value = text;
        ta.setAttribute("readonly", "");
        ta.style.position = "absolute";
        ta.style.left = "-9999px";
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand("copy"); done(); } catch (e) {
          if (status) status.textContent = "Copy failed. Select the number to copy it.";
        }
        document.body.removeChild(ta);
      }
    });
  }
})();
