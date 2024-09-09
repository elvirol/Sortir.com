let map //= null;
let marker //= null;

function init(){

    const latitude = document.getElementById('latitude').innerText;
    const longitude = document.getElementById('longitude').innerText;

    if(latitude && longitude) {

        map = L.map('map');

        //simple layer

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);
        map.setView([latitude, longitude], 12);

        marker = L.marker([latitude, longitude]).addTo(map);

        /*
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
        }).addTo(map);
        map.setView([latitude, longitude], 12);

        marker = L.marker([latitude, longitude]).addTo(map);
        */
    }
}

init();