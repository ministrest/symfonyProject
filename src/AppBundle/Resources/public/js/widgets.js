var scWidgets;

$(function(){
    scWidgets = new Widgets();
});

function Widgets(){
    this.hideClass = 'hide-row';
    this.rowSelector = "div[id^=row-fields-]";
    this.rowActiveClass = "table__row--active";
    
    var XHRGeoSearch = null;
    var that = this;
    
    /**
     * Return lazy load select2 parameters
     * @param {String} url
     * @param {String} placeholder
     * @param {Object} extra
     * @param {Boolean} isMultiple
     * @returns {Object}
     */
    this.getSearchEngine = function(url, placeholder, extra, isMultiple, name = null){
        var options = that.getSelectParams(true);
        
        options.placeholder = placeholder ? placeholder : "Выберите значение";
        options.multiple = (typeof isMultiple !== "undefined") ? isMultiple : false;
        
        options.ajax = {
            url: Routing.generate(url),
            dataType: 'json',
            delay: 1000,
            data: function (params) {
                var data = {"search": params};
                
                if (extra) {
                    $.each(extra, function(key, value){
                        data[key] = $(value).val() ? $(value).val() : null;
                    });
                }
                
                if (name) {
                    data['name'] = name;
                }
                    
                return data;
            },
            results: function (data) {
                var results = [];
                $.each(data, function(index, item){
                    var number = '';
                    switch (name) {
                        case 'licensePlate':
                          number = item.license_plate ? item.license_plate : 'без ГРЗ';
                          break;
                        case 'garageNumber':
                          number = item.garage_number ? item.garage_number : 'без номера';
                          break;
                        default:
                          number = item.title ? item.title :
                                 item.license_plate ? item.license_plate : 
                                 item.garage_number ? item.garage_number : 
                                 item.number ? item.number : item.full_name;
                    }
                    results.push({
                        id: item.id,
                        text: number
                    });
                });
            
                return {results: results};
            },
            cache: true
        }
        
        return options;
    };
    
    /**
     * Enable DateTimePicker
     * @param {string} selector
     */
    this.enableFlatPckr = function(selector) {
        var pickerInput = $(selector).not('.flatpickr-input');
        if (pickerInput.length) {
            pickerInput.flatpickr({
                "enableTime": true,
                "time_24hr": true,
                "inline": false,
                "clickOpens": false,
                "allowInput": false,
                onReady: function(dateObj, dateStr, instance) {
                    $('.flatpickr-calendar').each(function() {
                        var $this = $(this);
                        if ($this.find('.flatpickr-clear').length < 1) {
                            $this.append('<div class="flatpickr-clear button-modal">Очистить</div>');
                            $this.find('.flatpickr-clear').on('click', function() {
                                instance.clear();
                                instance.close();
                            });
                        }
                    });
                }
            });

            pickerInput.on("click", function (e) {
                var picker = e.target._flatpickr;
                picker.toggle();
            });
        }
    };
    
    this.getSelectParams = function(isAllowClear) {
        return {
            placeholder: "Выберите значение",
            formatNoMatches: function() { return "Значений не найдено"; },
            formatSearching: function() { return "Поиск..."; },
            allowClear: isAllowClear,
        }
    };
    
    /**
     * Get url for nominatim service
     * @param {String} type  ["reverse", "search"]
     * @returns {String}
     */
    this.getNominatimUrl = function(type){
        var url = $("#nominatim_server").val();
        if (url) {
            var catalog = $("#nominatim_server_catalog").val();
            var port = $("#nominatim_port").val();
            url = port ? url + ":" + port : url;
            url = catalog ? url + catalog : url; 
        
            return document.location.protocol + "//" + url + "/" + type;
        }
        
        return null;
    };
    
    /**
     * Get last code for emergency type and origin place
     * @param {String} url
     * @param {Object} data
     * @param {Object} $element
     * @returns {String}
     */
    this.getLastCode = function (url, data, $element){
        if (data) {
            $.ajax({
                url: url,
                type: 'POST',
                data: data,
                success: function (response) {
                    var data = JSON.parse(response)
                    if (data[0]) {
                        var code = (parseInt(data[0].code) + 1).toString();
                        code = code.length < 2 ? '0' + code : code;
                        
                        $element.val(code);
                    }
                }
            });
        }
    };
    
    /**
     * Print div content by selector
     * @param {string} selector
     * @param {string} title
     */
    this.printContent = function(selector, title) {
        var tableTxt = $(selector).html();
        var headTxt = $('head').html();
        var newWin = window.open("", title, "width=800,height=600,top=0,left=0,toolbar=no,scrollbars=no,status=no,resizable=no");
        
        newWin.document.open();
        newWin.document.writeln(
            '<html>' + 
                headTxt +
                '<body onload="window.print()">' +
                    '<div class="table__row"><h2>' + title + '</h2></div>' +
                    tableTxt +
                '</body>' +
            '</html>'
        );

        var css = newWin.document.createElement("style");
        css.type = "text/css";
        css.innerHTML = "@media print { \
            html {padding: 0px!important;} \
            .table__body {overflow: visible!important;} \
            .table__header, .table__body, .table__row { \
                border-bottom: solid 1px black; \
            }\
        }";
        
        newWin.document.body.appendChild(css);
        newWin.focus();
        newWin.document.close();
    };
    
    var request = function (url, callback) {
        if (XHRGeoSearch && XHRGeoSearch.readyState != 4) {
            XHRGeoSearch.abort();
            XHRGeoSearch = false;
        }

        var data;

        XHRGeoSearch = $.ajax({
            type: 'GET',
            dataType: 'json',
            url: url,
            success: function (result) {
                data = result;
            },
            error: function (error) {
                data = error;
            },
            complete: function () {
                if (callback) {
                    callback(data);
                }
            }
        });
    };
    
    /**
     * Get address by coordinate with nominatim
     * @param {Object} inputAddress
     * @param {Object} inputCoordnates
     * @param {L.LatLng} latlng
     */
    this.getAddressByCoordinates = function(latlng, inputAddress, inputCoordnates) {
        var url = scWidgets.getNominatimUrl("reverse");
        
        if (url) {
            request(url + "?format=json&lat=" + latlng.lat + "&lon=" + latlng.lng + "&zoom=18&addressdetails=1&accept-language=ru")
                .then(function (data) {
                    if (typeof data != "undefined" && typeof data.address != "undefined" && typeof data.address.road != "undefined") {
                        var house = typeof data.address.house_number != "undefined" ? ", " + data.address.house_number : "";
                        var address = data.address.road + house;
                        $(inputAddress).val(address);
                        
                        if (inputCoordnates) {
                            $(inputCoordnates).val("["+latlng.lat+','+latlng.lng+"]");
                        }
                    }
                });
        }
    };
    
    /**
     * Upper case first symbol
     * @param {String} str
     * @returns {String}
     */
    this.ucfirst = function(str) {
        var first = str.charAt(0).toUpperCase();
        return first + str.substr(1);
    };
    
    /**
     * Check address on error
     * 
     * @param {Object} input
     */
    this.checkAddressError = function(input) {
        var address = input.val().trim();
        if (address !== '') {
            var regexp = /[a-z]+$/i;
            if(regexp.test(address)) {
                return "Введите только русские буквы";
            }
            
           var abbreviation = ["улица", "проспект", "переулок", "шоссе", "ул", "ул.", "километр", "квартал", "кв-л",
                "просп", "просп.", "пер", "пер.", "ш", "ш.", "тупик", "пр-т", "проулок", "проулок", "остановка",
                "сад", "сквер", "станция", "тракт", "тупик", "туп", "туп.", "участок", "уч-к", "спуск",
                "проезд", "пр", "пр.", "набережная", "наб", "наб.", "площадь", "пл", "пл.", "платформа",
                "бульвар", "б-р", "аллея", "вал", "въезд", "дорога", "дор", "кольцо", "микрорайон", "мкр",
                "линия", "мост", "парк", "проезд", ""];

            var words = address.split(' ');
            if (words.length < 2) {
              return 'Недостаточно информации для поиска'
            }
            
            if (abbreviation.indexOf(words[0].toLowerCase()) == -1) {
                return 'Неверно указан элемент инфраструктуры';
            } else {
                words[1] = that.ucfirst(words[1]);
                input.val(words.join(' '));
            }
            return false;
        }
        
        return 'Введите адрес';
    };
    
    /**
     * Start searching coordinates by text address
     * @param {Object} map
     * @param {String} address
     */
    this.startSearchCoordidnatesByAddress = function(map, address){
        map.ControlProvider._searchbox.value = 'Москва, ' + address;
        map.ControlProvider.startSearch();
    };
    
    var request = function (url) {
        return $.ajax({
            type: 'GET',
            dataType: 'json',
            url: url
        });
    };
    
    /**
     *
     * @param {String} address
     * @returns string
     */
    this.updateDatalistByAddress = function(address, id) {
        var url = that.getNominatimUrl("search");
        if (url) {
            return request(url + '/Москва ' + address + "?format=json&addressdetails=1&limit=100&accept-language=ru")
                .then(function (data) {
                    if (data) {
                        $('datalist#' + id).html('');
                        $.each(data, function (key, value) {
                            if (typeof value.address != "undefined" && typeof value.address.road != "undefined") {
                                var house = typeof value.address.house_number != "undefined" ? ", " + value.address.house_number : "";
                                var place = value.address.road + house;
                                $('datalist#' + id).append("<option value='" + place + "'>" + place + "</option>");
                            }
                        });
                    }
                });
        }
    };
    
    /**
     * Hide block with selector
     * @param {string} selector
     */
    this.clearAndHideBlock = function(selector) {
        this.clearValuesInBlock(selector);
        $(selector).addClass(this.hideClass);
    };
    
    /**
     * Show block with selector
     * @param {string} selector
     */
    this.showBlock = function(selector) {
        $(selector).removeClass(this.hideClass);
    };
    
    /**
     * Clear input values in block with selector
     * @param {string} selector
     */
    this.clearValuesInBlock = function(selector) {
        $(selector).find('input').val('');
        $(selector).find('select').val(null).trigger('change');
    };
    
    /**
     * Clear input values in block with selector
     * @param {string} selector
     */
    this.clearSelect = function(selector) {
        $(selector).empty();
        $(selector).val(null).trigger('change');
    };
}