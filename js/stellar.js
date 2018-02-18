/**
 * JavaScript utility for Stellar Federation verification.
 *
 * This library contains functions related with Stellar Federation
 * verification and resolution.
 *
 * @author Helmuth Breitenfellner (helmuthb)
 * @since 1.1.0
 */
var stellarpressFederation = (function() {
    //
    return {
        inform_into: function(name, address, target) {
            var domain = window.location.hostname;
            if (domain.startsWith("www.")) {
                domain = domain.substring(4);
            }
            var fullname = name + "*" + domain;
            var element = document.getElementById(target);
            // Resolve the name
            StellarSdk.FederationServer.resolve(fullname)
            .then(function(federationRecord) {
                if (federationRecord.account_id === address) {
                    element.classList.add("status-ok");
                    element.innerHTML = "Success! <em>" + fullname + "</em> resolves to <em>" + address + "</em>";
                }
                else {
                    element.classList.add("status-error");
                    element.innerHTML = "Error! <em>" + fullname + "</em> resolves to <em>" + federationRecord.account_id + "</em>";
                }
            })
            .catch(function() {
                element.classList.add("status-warning");
                element.innerHTML = "Warning: Cannot resolve <em>" + fullname + "</em>";
            });
        },
    }
})();