import axios from "axios";
window.axios = axios;

window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

// Set withCredentials if you rely on cookies for auth. If you use Authorization header (recommended), this
// can remain false. Enable if you want the browser to send cookies to the API on cross-origin requests.
window.axios.defaults.withCredentials = false;

// Helper: read cookie by name
function readCookie(name) {
    const m = document.cookie.match(new RegExp("(^|; )" + name + "=([^;]*)"));
    return m ? decodeURIComponent(m[2]) : null;
}

// Try to set Authorization header from localStorage.jwt or cookie auth_token/token
const savedToken =
    typeof localStorage !== "undefined" ? localStorage.getItem("jwt") : null;
const cookieToken = readCookie("auth_token") || readCookie("token");
const token = savedToken || cookieToken;
if (token) {
    window.axios.defaults.headers.common["Authorization"] = "Bearer " + token;
}

// Small helper to set token on successful login
window.setAuthToken = function (t) {
    if (!t) return;
    try {
        localStorage.setItem("jwt", t);
    } catch (e) {
        /* ignore storage errors */
    }
    window.axios.defaults.headers.common["Authorization"] = "Bearer " + t;
};
