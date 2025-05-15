// fuse-search.js - versión-comentada-todas-palabras

// Espera a que el DOM esté completamente cargado antes de ejecutar el código
document.addEventListener("DOMContentLoaded", function () {
  const wrappers = document.querySelectorAll(".fuse-search-wrapper");
  if (!wrappers.length) return; // Si no hay ningún wrapper de búsqueda, salir

  // Procesa cada instancia de wrapper individual
  wrappers.forEach((wrapper) => {
    // Extrae el layout y la redirección (en caso de layout tipo "barra")
    const layout = wrapper.getAttribute("data-layout") || "default";
    const redirect = wrapper.getAttribute("data-redirect") || "";
    const jsonScripts = wrapper.querySelectorAll("script.fuse-json");

    // === LAYOUT "barra": Redirección a resultados con query ===
    if (layout === "barra") {
      const input = wrapper.querySelector("input");
      wrapper.querySelector("form").addEventListener("submit", function (event) {
        event.preventDefault();
        const q = input.value.trim();
        if (q && redirect) {
          window.location.href = `${redirect}?q=${encodeURIComponent(q)}`;
        }
      });
      return;
    }

    // === VARIABLES COMUNES DE BÚSQUEDA ===
    const input = wrapper.querySelector("input");
    const resultsContainer = wrapper.querySelector("#fuse-results") || document.createElement("ul");
    const filterContainer = wrapper.querySelector("#fuse-filters") || document.createElement("div");
    const paginationContainer = wrapper.querySelector("#fuse-pagination") || document.createElement("div");

    let labeledData = {}; // Contendrá los datos agrupados por archivo JSON (etiqueta)
    let selectedLabels = new Set(); // Conjunto de filtros seleccionados
    let currentPage = 1; // Página actual
    let resultsPerPage = 20; // Resultados por página
    let currentResults = []; // Resultados actuales paginados

    // Formatea el nombre del filtro visible (corrige prefijos y formato)
    const formatLabelName = (raw) => raw.replace(/^cat|etiq|pag/, '').replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());

    // Devuelve el valor de un parámetro del query string
    const getURLQuery = (key) => new URLSearchParams(window.location.search).get(key) || '';

    // Verifica si estamos en la página de resultados
    const isResultadosPage = () => window.location.pathname.includes('/resultados');

    // === CARGA LOS ARCHIVOS JSON DE CONTENIDO Y CONSTRUYE LOS FILTROS ===
    const loadAllJSON = async () => {
      for (const script of jsonScripts) {
        const src = script.getAttribute("data-src");
        try {
          const response = await fetch(src);
          const json = await response.json();
         const labelValue = script.getAttribute("data-label") || "otros";
const displayName = formatLabelName(labelValue);

// Acumula datos por etiqueta (label)
if (!labeledData[labelValue]) {
  labeledData[labelValue] = [...json];

  const checkbox = document.createElement("label");
  checkbox.classList.add("fuse-filter-label");
  checkbox.innerHTML = `<input type="checkbox" value="${labelValue}"> <span>${displayName}</span>`;
  filterContainer.appendChild(checkbox);
} else {
  labeledData[labelValue] = labeledData[labelValue].concat(json);
}

        } catch (e) {
          console.error("Error cargando JSON:", src);
        }
      }

      // Inserta el mensaje de sugerencia de búsqueda
    //  const tooltip = document.createElement("div");
   //   tooltip.className = "fuse-tooltip-dark";
    //  tooltip.innerHTML = `
       // <span class="fuse-tooltip-target" aria-label="Sugerencia búsqueda eficaz">💡¿Busqueda más eficaz?
         // <span class="fuse-tooltip-bubble">
           // <strong>Áreas Instituciones DGE:</strong> Use esta opción cuando necesite información sobre direcciones o coordinaciones de la DGE.<br>
         //   <strong>Noticias / Recursos / Servicios:</strong> Tilde la/s casilla/s para acelerar la búsqueda.
        //  </span>
       // </span>`;
     // filterContainer.prepend(tooltip);

      // Preselecciona todos los filtros solo en la página de resultados (excepto en educar2)
      if (isResultadosPage() && layout !== "educar2") {
        filterContainer.querySelectorAll("input[type=checkbox]").forEach(cb => cb.checked = true);
      }

      // Carga el parámetro q si está presente en la URL
      const q = getURLQuery('q');
      if (q && input) input.value = q;

      searchAndRender();
    };

    // === PROCESA LA BÚSQUEDA Y RENDERIZA RESULTADOS ===
    const searchAndRender = () => {
      const query = input.value.trim().toLowerCase();
      const minLetters = 5;
      const minWords = 1;

      // Validación mínima de búsqueda
      if (query.length < minLetters && query.split(/\s+/).length < minWords) {
        renderResults([]);
        paginationContainer.innerHTML = "";
        return;
      }

      // Recoge los filtros activos
      selectedLabels = new Set(
        Array.from(filterContainer.querySelectorAll("input[type=checkbox]:checked")).map(cb => cb.value)
      );

      // Filtra el contenido según los filtros seleccionados
      const filteredData = selectedLabels.size > 0
        ? Object.entries(labeledData)
            .filter(([label]) => selectedLabels.has(label))
            .flatMap(([, posts]) => posts)
        : Object.values(labeledData).flat();

      if (!filteredData.length && !query) {
        renderResults([]);
        return;
      }

      // Inicializa Fuse.js con la configuración base
      const fuse = new Fuse(filteredData, {
        keys: ["title", "excerpt"],
        includeScore: true,
        threshold: 0.4,
        ignoreLocation: true,
        findAllMatches: false,
        minMatchCharLength: 3
      });

      // Divide el query en palabras individuales
      const queryWords = query.split(/\s+/).filter(Boolean);

      // Realiza la búsqueda difusa con Fuse.js
      const rawResults = fuse.search(query);

      // FILTRO PERSONALIZADO: Mantiene solo resultados donde TODAS las palabras están presentes
      const filteredResults = rawResults.filter(r => {
        const content = `${r.item.title} ${r.item.excerpt}`.toLowerCase();
        return queryWords.every(word => content.includes(word));
      });

      // Extrae solo los ítems y ordena por fecha descendente
     const results = filteredResults
  .map(r => r.item)
  .filter((item, index, self) => self.findIndex(i => i.link === item.link) === index) // evita duplicados exactos
  .sort((a, b) => new Date(b.date) - new Date(a.date)); // ordena por fecha reciente


      currentResults = results;
      currentPage = 1;
      renderPaginatedResults();
    };

    // === RENDERIZA LOS RESULTADOS PAGINADOS ===
    const renderPaginatedResults = () => {
      const start = (currentPage - 1) * resultsPerPage;
      const end = start + resultsPerPage;
      const pageItems = currentResults.slice(start, end);

      renderResults(pageItems);
      renderPagination(currentResults.length);
    };

    // === IMPRIME LOS RESULTADOS EN EL DOM ===
    const renderResults = (items) => {
      resultsContainer.innerHTML = "";

      if (!items.length) {
        resultsContainer.innerHTML = `<li class="fuse-no-results">⚠️ No se encontraron resultados.</li>`;
        paginationContainer.innerHTML = "";
        return;
      }

      for (const item of items) {
        if (!item || !item.title) continue;

        const shortExcerpt = item.excerpt.split(" ").slice(0, 20).join(" ") + "...";
        const formattedDate = formatDate(item.date);

        const li = document.createElement("li");
        li.classList.add("fuse-result-box");

        li.innerHTML = `
          <div class="fuse-result-item">
            ${item.image ? `<img src="${item.image}" alt="">` : ""}
            <div class="fuse-result-info">
              <a href="${item.link}" target="_blank"><strong>${item.title}</strong></a>
              <p>${shortExcerpt}</p>
              <small>${formattedDate}</small>
            </div>
          </div>`;
        resultsContainer.appendChild(li);
      }
    };

    // === FORMATEA FECHAS EN FORMATO DD/MM/YYYY ===
    function formatDate(isoString) {
      if (!isoString) return "";
      const date = new Date(isoString);
      const day = String(date.getDate()).padStart(2, '0');
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const year = date.getFullYear();
      return `${day}/${month}/${year}`;
    }

    // === CONSTRUYE LA PAGINACIÓN DE RESULTADOS ===
    const renderPagination = (totalItems) => {
      const totalPages = Math.ceil(totalItems / resultsPerPage);
      paginationContainer.innerHTML = "";
      if (totalPages <= 1) return;

      const nav = document.createElement("div");
      nav.classList.add("fuse-pagination-nav");

      const counter = document.createElement("div");
      counter.classList.add("fuse-pagination-counter");
      counter.textContent = `Total resultados: ${totalItems} | Páginas: ${totalPages}`;
      nav.appendChild(counter);

      if (currentPage > 1) {
        const prev = document.createElement("button");
        prev.textContent = "← Anterior";
        prev.onclick = () => { currentPage--; renderPaginatedResults(); };
        nav.appendChild(prev);
      }

      const maxVisible = 20;
      let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
      let endPage = startPage + maxVisible - 1;
      if (endPage > totalPages) {
        endPage = totalPages;
        startPage = Math.max(1, endPage - maxVisible + 1);
      }

      if (startPage > 1) {
        const firstPage = document.createElement("button");
        firstPage.textContent = "1";
        firstPage.onclick = () => { currentPage = 1; renderPaginatedResults(); };
        nav.appendChild(firstPage);

        if (startPage > 2) {
          const dots = document.createElement("span");
          dots.textContent = "...";
          nav.appendChild(dots);
        }
      }

      for (let i = startPage; i <= endPage; i++) {
        const pageButton = document.createElement("button");
        pageButton.textContent = i;
        if (i === currentPage) {
          pageButton.disabled = true;
          pageButton.style.backgroundColor = "#ccc";
        }
        pageButton.onclick = () => {
          currentPage = i;
          renderPaginatedResults();
        };
        nav.appendChild(pageButton);
      }

      if (endPage < totalPages) {
        const dots = document.createElement("span");
        dots.textContent = "...";
        nav.appendChild(dots);

        const lastPage = document.createElement("button");
        lastPage.textContent = totalPages;
        lastPage.onclick = () => { currentPage = totalPages; renderPaginatedResults(); };
        nav.appendChild(lastPage);
      }

      if (currentPage < totalPages) {
        const next = document.createElement("button");
        next.textContent = "Siguiente →";
        next.onclick = () => { currentPage++; renderPaginatedResults(); };
        nav.appendChild(next);
      }

      paginationContainer.appendChild(nav);
    };

    // === EVENTOS DE BÚSQUEDA ===
    input.addEventListener("input", debounce(searchAndRender, 200));
    filterContainer.addEventListener("change", () => searchAndRender());

    // === FUNCIÓN DE DEBOUNCE PARA EVITAR LLAMADAS EXCESIVAS ===
    function debounce(fn, delay) {
      let timeout;
      return function () {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn(), delay);
      };
    }

    // Inicia la carga de los JSON y el proceso
    loadAllJSON();
  });
});
