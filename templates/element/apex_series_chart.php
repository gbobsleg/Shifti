<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
window.renderApexLine = function(containerId, categories, series, options = {}) {
  const el = document.getElementById(containerId);
  if (!el) return;
  
  // Détecter si on a plusieurs jours (categories contiennent "DD/MM")
  const hasMultipleDays = categories.length > 0 && categories[0].includes('/');
  
  const chartOptions = {
    connectNulls: false,
    chart: { 
      type: 'line', 
      height: 450,
      toolbar: {
        show: true,
        tools: {
          download: true,
          zoom: true,
          zoomin: true,
          zoomout: true,
          pan: true,
          reset: true
        }
      }
    },
    xaxis: { 
      categories,
      labels: {
        rotate: -45,
        rotateAlways: false,
        hideOverlappingLabels: true,
        trim: false,
        minHeight: 80,
        maxHeight: 120,
        style: {
          fontSize: '9px'
        },
        formatter: function(value, timestamp, opts) {
          if (!value) return '';
          // Vérifier que opts existe et a dataPointIndex
          if (!opts || typeof opts.dataPointIndex === 'undefined') {
            return value;
          }
          
          // Si format "DD/MM HH:mm", affichage optimisé pour plusieurs jours
          if (hasMultipleDays && value.includes(' ')) {
            const parts = value.split(' ');
            const day = parts[0];
            const time = parts[1];
            const index = opts.dataPointIndex;
            
            // Stratégie d'affichage :
            // - Jour affiché uniquement au changement de jour (+ premier point)
            // - Heure affichée toutes les 2 heures minimum (8 tranches de 15min)
            
            const totalPoints = categories.length;
            let showInterval = 8; // Par défaut toutes les 2h
            
            // Adapter l'intervalle selon le nombre de points
            if (totalPoints > 1000) showInterval = 64;      // > 10 jours : toutes les 16h
            else if (totalPoints > 500) showInterval = 32;  // > 5 jours : toutes les 8h
            else if (totalPoints > 200) showInterval = 16;  // > 2 jours : toutes les 4h
            
            // Changement de jour ? Afficher jour + heure
            if (index === 0 || (index > 0 && categories[index - 1] && categories[index - 1].split(' ')[0] !== day)) {
              return day + '\n' + time;
            }
            
            // Afficher juste l'heure selon l'intervalle
            if (index % showInterval === 0) {
              return time;
            }
            
            // Sinon : label vide
            return '';
          }
          return value;
        }
      },
      tickAmount: hasMultipleDays ? 10 : undefined,
      tickPlacement: 'on'
    },
    series,
    stroke: { width: 2, curve: 'smooth' },
    dataLabels: { enabled: false },
    legend: { position: 'top' },
    grid: {
      borderColor: '#e7e7e7',
      row: {
        colors: ['#f3f3f3', 'transparent'],
        opacity: 0.5
      }
    },
    tooltip: {
      x: {
        show: true
      }
    },
    ...options
  };
  el.innerHTML = '';
  const chart = new ApexCharts(el, chartOptions);
  chart.render();
  return chart;
}

window.renderApexStacked = function(containerId, categories, series, customOptions = {}) {
  const el = document.getElementById(containerId);
  if (!el) return;
  
  // Détecter si on a plusieurs jours
  const hasMultipleDays = categories.length > 0 && categories[0].includes('/');
  
  const options = {
    chart: { 
      type: 'bar', 
      height: 450, 
      stacked: true, 
      stackType: 'normal',
      toolbar: {
        show: true,
        tools: {
          download: true,
          zoom: true,
          zoomin: true,
          zoomout: true,
          pan: true,
          reset: true
        }
      }
    },
    plotOptions: {
      bar: { 
        columnWidth: '95%', 
        dataLabels: { position: 'center' },
        borderRadius: 0
      }
    },
    xaxis: { 
      categories,
      labels: {
        rotate: -45,
        rotateAlways: false,
        hideOverlappingLabels: true,
        trim: false,
        minHeight: 80,
        maxHeight: 120,
        style: {
          fontSize: '9px'
        },
        formatter: function(value, timestamp, opts) {
          if (!value) return '';
          if (!opts || typeof opts.dataPointIndex === 'undefined') {
            return value;
          }
          
          // Affichage optimisé pour plusieurs jours
          if (hasMultipleDays && value.includes(' ')) {
            const parts = value.split(' ');
            const day = parts[0];
            const time = parts[1];
            const index = opts.dataPointIndex;
            
            const totalPoints = categories.length;
            let showInterval = 8;
            
            if (totalPoints > 1000) showInterval = 64;
            else if (totalPoints > 500) showInterval = 32;
            else if (totalPoints > 200) showInterval = 16;
            
            // Changement de jour
            if (index === 0 || (index > 0 && categories[index - 1] && categories[index - 1].split(' ')[0] !== day)) {
              return day + '\n' + time;
            }
            
            // Heures selon intervalle
            if (index % showInterval === 0) {
              return time;
            }
            
            return '';
          }
          return value;
        }
      },
      tickAmount: hasMultipleDays ? 10 : undefined,
      tickPlacement: 'on'
    },
    series,
    dataLabels: { enabled: false },
    legend: { 
      position: 'top',
      horizontalAlign: 'left'
    },
    colors: ['#28a745', '#dc3545', '#6c757d'],
    grid: {
      borderColor: '#e7e7e7',
      row: {
        colors: ['#f3f3f3', 'transparent'],
        opacity: 0.5
      }
    },
    tooltip: {
      shared: true,
      intersect: false
    },
    ...customOptions
  };
  el.innerHTML = '';
  const chart = new ApexCharts(el, options);
  chart.render();
  return chart;
}

window.renderApexArea = function(containerId, categories, series, options = {}) {
  const el = document.getElementById(containerId);
  if (!el) return;
  
  // Détecter si on a plusieurs jours
  const hasMultipleDays = categories.length > 0 && categories[0].includes('/');
  console.log('[renderApexArea] Nombre total de catégories:', categories.length);
  
  const chartOptions = {
    chart: { 
      type: 'area',
      height: 450,
      animations: {
        enabled: true,
        easing: 'easeinout',
        speed: 400,
        animateGradually: {
          enabled: false
        },
        dynamicAnimation: {
          enabled: true,
          speed: 200
        }
      },
      toolbar: {
        show: true,
        tools: {
          download: true,
          zoom: true,
          zoomin: true,
          zoomout: true,
          pan: true,
          reset: true
        }
      },
      zoom: {
        enabled: true,
        type: 'x',
        autoScaleYaxis: true
      }
    },
    xaxis: { 
      categories,
      labels: {
        rotate: -45,
        rotateAlways: false,
        hideOverlappingLabels: true,
        trim: false,
        minHeight: 80,
        maxHeight: 120,
        style: {
          fontSize: '9px'
        },
        formatter: function(value, timestamp, opts) {
          if (!value) return '';
          if (!opts || typeof opts.dataPointIndex === 'undefined') {
            return value;
          }
          
          if (hasMultipleDays && value.includes(' ')) {
            const parts = value.split(' ');
            const dateWithYear = parts[0]; // Format DD/MM/YYYY
            const index = opts.dataPointIndex;
            const totalPoints = categories.length;
            
            // Debug premier point
            if (index === 0) {
              console.log('[renderApexArea formatter] totalPoints:', totalPoints, '> 288?', totalPoints > 288);
            }
            
            // Pour les plages longues (> 3 jours), afficher SEULEMENT les dates (pas d'heures)
            if (totalPoints > 288) {
              // Afficher la date uniquement au changement de jour
              if (index === 0 || (index > 0 && categories[index - 1] && categories[index - 1].split(' ')[0] !== dateWithYear)) {
                return dateWithYear;
              }
              return '';
            }
            
            // Pour les plages courtes (≤ 3 jours), afficher date + heures
            const time = parts[1]; // Format HH:mm
            let showInterval = 8;
            if (totalPoints > 200) showInterval = 16;
            
            // Changement de jour ? Afficher date + heure
            if (index === 0 || (index > 0 && categories[index - 1] && categories[index - 1].split(' ')[0] !== dateWithYear)) {
              return dateWithYear + '\n' + time;
            }
            
            // Afficher juste l'heure
            if (index % showInterval === 0) {
              return time;
            }
            
            return '';
          }
          return value;
        }
      }
    },
    series,
    stroke: { 
      width: 2, 
      curve: 'smooth' 
    },
    connectNulls: false,
    fill: {
      type: 'gradient',
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.7,
        opacityTo: 0.3,
        stops: [0, 90, 100]
      }
    },
    markers: {
      size: 0
    },
    dataLabels: { enabled: false },
    legend: { 
      position: 'top',
      horizontalAlign: 'left'
    },
    grid: {
      borderColor: '#e7e7e7',
      row: {
        colors: ['#f3f3f3', 'transparent'],
        opacity: 0.5
      }
    },
    tooltip: {
      shared: true,
      intersect: false
    },
    ...options
  };
  el.innerHTML = '';
  const chart = new ApexCharts(el, chartOptions);
  chart.render();
  return chart;
}

window.renderApexMixed = function(containerId, categories, forecastData, needData) {
  const el = document.getElementById(containerId);
  if (!el) return;
  
  // Détecter si on a plusieurs jours
  const hasMultipleDays = categories.length > 0 && categories[0].includes('/');
  
  const options = {
    connectNulls: false,
    chart: { 
      type: 'line',
      height: 450,
      toolbar: {
        show: true,
        tools: {
          download: true,
          zoom: true,
          zoomin: true,
          zoomout: true,
          pan: true,
          reset: true
        }
      }
    },
    series: [
      {
        name: 'Forecast (Volume)',
        type: 'line',
        data: forecastData
      },
      {
        name: 'Need (Besoin)',
        type: 'column',
        data: needData
      }
    ],
    stroke: {
      width: [3, 0],
      curve: 'smooth'
    },
    plotOptions: {
      bar: {
        columnWidth: '50%'
      }
    },
    colors: ['#007bff', '#28a745'],
    xaxis: { 
      categories,
      labels: {
        rotate: -45,
        rotateAlways: false,
        hideOverlappingLabels: true,
        trim: false,
        minHeight: 80,
        maxHeight: 120,
        style: {
          fontSize: '9px'
        },
        formatter: function(value, timestamp, opts) {
          if (!value) return '';
          if (!opts || typeof opts.dataPointIndex === 'undefined') {
            return value;
          }
          
          if (hasMultipleDays && value.includes(' ')) {
            const parts = value.split(' ');
            const dateWithYear = parts[0]; // Format DD/MM/YYYY
            const time = parts[1];         // Format HH:mm
            const index = opts.dataPointIndex;
            const totalPoints = categories.length;
            
            // Pour les plages longues (> 3 jours), afficher SEULEMENT les dates (pas d'heures)
            if (totalPoints > 288) { // 288 tranches = 3 jours
              // Afficher la date uniquement au changement de jour
              if (index === 0 || (index > 0 && categories[index - 1] && categories[index - 1].split(' ')[0] !== dateWithYear)) {
                return dateWithYear;
              }
              return '';
            }
            
            // Pour les plages courtes (≤ 3 jours), afficher date + heures
            let showInterval = 8; // Toutes les 2h par défaut
            if (totalPoints > 200) showInterval = 16; // > 2 jours : toutes les 4h
            
            // Changement de jour ? Afficher date + heure
            if (index === 0 || (index > 0 && categories[index - 1] && categories[index - 1].split(' ')[0] !== dateWithYear)) {
              return dateWithYear + '\n' + time;
            }
            
            // Afficher juste l'heure
            if (index % showInterval === 0) {
              return time;
            }
            
            return '';
          }
          return value;
        }
      },
      tickAmount: hasMultipleDays ? 10 : undefined,
      tickPlacement: 'on'
    },
    yaxis: [
      {
        title: {
          text: 'Volume'
        }
      }
    ],
    legend: {
      position: 'top',
      horizontalAlign: 'left',
      offsetY: 0
    },
    dataLabels: {
      enabled: false
    },
    grid: {
      borderColor: '#e7e7e7',
      row: {
        colors: ['#f3f3f3', 'transparent'],
        opacity: 0.5
      }
    },
    tooltip: {
      shared: true,
      intersect: false
    }
  };
  el.innerHTML = '';
  const chart = new ApexCharts(el, options);
  chart.render();
  return chart;
}
</script>




