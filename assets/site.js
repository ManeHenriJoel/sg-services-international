document.addEventListener('DOMContentLoaded', () => {
  const menuButton = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.main-nav');
  menuButton?.addEventListener('click', () => {
    const expanded = menuButton.getAttribute('aria-expanded') === 'true';
    menuButton.setAttribute('aria-expanded', String(!expanded));
    nav?.classList.toggle('open', !expanded);
  });
  document.querySelector('.service-filter-form select')?.addEventListener('change', (event) => {
    event.currentTarget.form?.requestSubmit();
  });

  document.querySelectorAll('[data-carousel-prev], [data-carousel-next]').forEach((button) => {
    button.addEventListener('click', () => {
      const carouselName = button.dataset.carouselPrev || button.dataset.carouselNext;
      const carousel = [...document.querySelectorAll('[data-carousel]')].find((item) => item.dataset.carousel === carouselName);
      if (!carousel) return;
      const card = carousel.querySelector(':scope > *');
      const distance = card ? card.getBoundingClientRect().width + parseFloat(getComputedStyle(carousel).gap || '0') : carousel.clientWidth * 0.8;
      carousel.scrollBy({ left: button.hasAttribute('data-carousel-next') ? distance : -distance, behavior: 'smooth' });
    });
  });

  const hero = document.querySelector('.hero-image[data-hero-slides]');
  if (hero) {
    const slides = JSON.parse(hero.dataset.heroSlides || '[]');
    if (slides.length > 1) {
      let activeIndex = 0;
      const showSlide = (nextIndex) => {
        activeIndex = (nextIndex + slides.length) % slides.length;
        const slide = slides[activeIndex];
        hero.style.backgroundImage = `linear-gradient(180deg, #071d2410 44%, #071d24b8), url("${slide.src}")`;
        hero.setAttribute('aria-label', slide.alt);
        hero.querySelector('[data-hero-title]').textContent = slide.title;
        hero.querySelector('[data-hero-counter]').textContent = `${String(activeIndex + 1).padStart(2, '0')} / ${String(slides.length).padStart(2, '0')}`;
      };
      hero.querySelector('[data-hero-prev]')?.addEventListener('click', () => showSlide(activeIndex - 1));
      hero.querySelector('[data-hero-next]')?.addEventListener('click', () => showSlide(activeIndex + 1));
      if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        window.setInterval(() => showSlide(activeIndex + 1), 7000);
      }
    }
  }

  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const observer = new IntersectionObserver((entries, currentObserver) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          currentObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -30px 0px' });
    document.querySelectorAll('main > section, main > form.glass-search, .property-card, .project-card').forEach((element, index) => {
      element.classList.add('reveal-on-scroll');
      element.style.setProperty('--reveal-delay', `${Math.min(index % 5, 4) * 70}ms`);
      observer.observe(element);
    });
  }

  const mapElement = document.querySelector('#property-map');
  if (mapElement && window.L) {
    const properties = JSON.parse(mapElement.dataset.properties || '[]');
    const map = L.map(mapElement).setView([5.36, -4.01], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);
    const markers = [];
    properties.forEach((property) => {
      if (!Number.isFinite(property.lat) || !Number.isFinite(property.lng)) return;
      const marker = L.marker([property.lat, property.lng]).addTo(map);
      const popup = document.createElement('div');
      popup.className = 'map-popup';
      const title = document.createElement('b');
      title.textContent = property.title;
      const facts = document.createElement('span');
      facts.textContent = `${new Intl.NumberFormat('fr-FR').format(property.price)} FCFA · ${new Intl.NumberFormat('fr-FR').format(property.area)} m²`;
      const link = document.createElement('a');
      link.href = `?page=terrain&id=${encodeURIComponent(property.id)}`;
      link.textContent = 'Voir la fiche →';
      popup.append(title, facts, link);
      marker.bindPopup(popup);
      markers.push(marker);
    });
    if (markers.length > 1) map.fitBounds(L.featureGroup(markers).getBounds().pad(0.15));
  }

  const detailMap = document.querySelector('#detail-map');
  if (detailMap && window.L) {
    const latitude = Number(detailMap.dataset.lat);
    const longitude = Number(detailMap.dataset.lng);
    const map = L.map(detailMap).setView([latitude, longitude], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);
    L.marker([latitude, longitude]).addTo(map).bindPopup(detailMap.dataset.title).openPopup();
  }
});