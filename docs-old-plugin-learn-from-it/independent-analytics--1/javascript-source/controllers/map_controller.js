import {Controller} from "@hotwired/stimulus"
import {isDarkMode} from "../utils/appearance";
import svgMap from 'svgmap';


export default class extends Controller {
    static targets = ["chart"]
    static values = {
        data: Array,
        flagsUrl: String,
        locale: String
    }

    map = null
    resizeObserver = null

    connect() {
        if(this.map) {
            this.resizeObserver.observe(this.chartTarget);
            return
        }

        this.initializeMap()

        window.iawpMaps = window.iawpMaps || []
        window.iawpMaps.push(this.map)

        this.resizeObserver.observe(this.chartTarget);
    }

    disconnect() {
        this.resizeObserver.disconnect();
        window.iawpMaps = window.iawpMaps || []
        window.iawpMaps = window.iawpMaps.filter(map => {
            return map !== this.map
        })
    }

    initializeMap() {
        const id = 'iawp-map-' + Math.random().toString()
        this.chartTarget.setAttribute('id', id)

        const chartValues = {}

        this.dataValue.forEach((country) => {
            chartValues[country.country_code] = {
                visitors: country.visitors,
                views: country.views,
                sessions: country.sessions,
            }
        })

        this.map = new svgMap({
            targetElementID: id,
            allowInteraction: false,
            initialZoom: 1.1,
            colorMax: '#5123A0',
            colorMin: '#bea4eb',
            colorNoData: '#dedede',
            ratioType: 'log',
            data: {
                data: {
                    visitors: {
                        name: 'Visitors',
                    },
                    views: {
                        name: 'Views',
                    },
                    sessions: {
                        name: 'Sessions',
                    }

                },
                applyData: 'visitors',
                values: chartValues,
            },
            onGetTooltip: (tooltipDiv, countryCode, countryValues) => {
                const country = this.dataValue.find((country) => {
                    return country['country_code'] === countryCode
                })

                const flagUrl = this.flagsUrlValue + '/' + countryCode.toLowerCase() + '.svg'

                // Render an empty tooltip when there's no country data
                if(!country) {
                    const templateString =  `
                        <div class="iawp-geo-chart-tooltip">
                            <img src="${flagUrl}" alt="Country flag"/>
                            <h1>${this.countryName(countryCode)}</h1>
                            <p>No data available</p>
                        </div>
                    `;

                    const templateDocument= new DOMParser().parseFromString(templateString, "text/html");

                    return templateDocument.body.firstElementChild
                }

                const formatted_views = this.formatNumber(country['views'])
                const formatted_visitors = this.formatNumber(country['visitors'])
                const formatted_sessions = this.formatNumber(country['sessions'])

                const templateString =  `
                    <div class="iawp-geo-chart-tooltip">
                        <img src="${flagUrl}" alt="Country flag"/>
                        <h1>${this.countryName(countryCode)}</h1>
                        <div class="iawp-geo-chart-tooltip-table">
                            <span>${iawpText.views}</span><span>${formatted_views}</span>
                            <span>${iawpText.visitors}</span><span>${formatted_visitors}</span>
                            <span>${iawpText.sessions}</span><span>${formatted_sessions}</span>
                        </div>
                    </div>
                `;

                const templateDocument= new DOMParser().parseFromString(templateString, "text/html");

                return templateDocument.body.firstElementChild
            }
        })

        this.resizeObserver = new ResizeObserver(() => {
            if(!this.chartTarget.checkVisibility()) {
                return
            }

            this.map.mapPanZoom.resize();
            this.map.mapPanZoom.fit();
            this.map.mapPanZoom.center();
            this.map.mapPanZoom.zoom(1.1);
        });
    }

    formatNumber(number) {
        return new Intl.NumberFormat(this.localeValue, {
            maximumFractionDigits: 0
        }).format(number);
    }

    countryName(countryCode) {
        const countries = {
            AF: 'Afghanistan',
            AX: 'Åland Islands',
            AL: 'Albania',
            DZ: 'Algeria',
            AS: 'American Samoa',
            AD: 'Andorra',
            AO: 'Angola',
            AI: 'Anguilla',
            AQ: 'Antarctica',
            AG: 'Antigua and Barbuda',
            AR: 'Argentina',
            AM: 'Armenia',
            AW: 'Aruba',
            AU: 'Australia',
            AT: 'Austria',
            AZ: 'Azerbaijan',
            BS: 'Bahamas',
            BH: 'Bahrain',
            BD: 'Bangladesh',
            BB: 'Barbados',
            BY: 'Belarus',
            BE: 'Belgium',
            BZ: 'Belize',
            BJ: 'Benin',
            BM: 'Bermuda',
            BT: 'Bhutan',
            BO: 'Bolivia',
            BA: 'Bosnia and Herzegovina',
            BW: 'Botswana',
            BR: 'Brazil',
            IO: 'British Indian Ocean Territory',
            VG: 'British Virgin Islands',
            BN: 'Brunei Darussalam',
            BG: 'Bulgaria',
            BF: 'Burkina Faso',
            BI: 'Burundi',
            KH: 'Cambodia',
            CM: 'Cameroon',
            CA: 'Canada',
            CV: 'Cape Verde',
            BQ: 'Caribbean Netherlands',
            KY: 'Cayman Islands',
            CF: 'Central African Republic',
            TD: 'Chad',
            CL: 'Chile',
            CN: 'China',
            CX: 'Christmas Island',
            CC: 'Cocos Islands',
            CO: 'Colombia',
            KM: 'Comoros',
            CG: 'Congo',
            CK: 'Cook Islands',
            CR: 'Costa Rica',
            HR: 'Croatia',
            CU: 'Cuba',
            CW: 'Curaçao',
            CY: 'Cyprus',
            CZ: 'Czech Republic',
            CD: 'Democratic Republic of the Congo',
            DK: 'Denmark',
            DJ: 'Djibouti',
            DM: 'Dominica',
            DO: 'Dominican Republic',
            EC: 'Ecuador',
            EG: 'Egypt',
            SV: 'El Salvador',
            GQ: 'Equatorial Guinea',
            ER: 'Eritrea',
            EE: 'Estonia',
            ET: 'Ethiopia',
            FK: 'Falkland Islands',
            FO: 'Faroe Islands',
            FM: 'Federated States of Micronesia',
            FJ: 'Fiji',
            FI: 'Finland',
            FR: 'France',
            GF: 'French Guiana',
            PF: 'French Polynesia',
            TF: 'French Southern Territories',
            GA: 'Gabon',
            GM: 'Gambia',
            GE: 'Georgia',
            DE: 'Germany',
            GH: 'Ghana',
            GI: 'Gibraltar',
            GR: 'Greece',
            GL: 'Greenland',
            GD: 'Grenada',
            GP: 'Guadeloupe',
            GU: 'Guam',
            GT: 'Guatemala',
            GN: 'Guinea',
            GW: 'Guinea-Bissau',
            GY: 'Guyana',
            HT: 'Haiti',
            HN: 'Honduras',
            HK: 'Hong Kong',
            HU: 'Hungary',
            IS: 'Iceland',
            IN: 'India',
            ID: 'Indonesia',
            IR: 'Iran',
            IQ: 'Iraq',
            IE: 'Ireland',
            IM: 'Isle of Man',
            IL: 'Israel',
            IT: 'Italy',
            CI: 'Ivory Coast',
            JM: 'Jamaica',
            JP: 'Japan',
            JE: 'Jersey',
            JO: 'Jordan',
            KZ: 'Kazakhstan',
            KE: 'Kenya',
            KI: 'Kiribati',
            XK: 'Kosovo',
            KW: 'Kuwait',
            KG: 'Kyrgyzstan',
            LA: 'Laos',
            LV: 'Latvia',
            LB: 'Lebanon',
            LS: 'Lesotho',
            LR: 'Liberia',
            LY: 'Libya',
            LI: 'Liechtenstein',
            LT: 'Lithuania',
            LU: 'Luxembourg',
            MO: 'Macau',
            MK: 'Macedonia',
            MG: 'Madagascar',
            MW: 'Malawi',
            MY: 'Malaysia',
            MV: 'Maldives',
            ML: 'Mali',
            MT: 'Malta',
            MH: 'Marshall Islands',
            MQ: 'Martinique',
            MR: 'Mauritania',
            MU: 'Mauritius',
            YT: 'Mayotte',
            MX: 'Mexico',
            MD: 'Moldova',
            MC: 'Monaco',
            MN: 'Mongolia',
            ME: 'Montenegro',
            MS: 'Montserrat',
            MA: 'Morocco',
            MZ: 'Mozambique',
            MM: 'Myanmar',
            NA: 'Namibia',
            NR: 'Nauru',
            NP: 'Nepal',
            NL: 'Netherlands',
            NC: 'New Caledonia',
            NZ: 'New Zealand',
            NI: 'Nicaragua',
            NE: 'Niger',
            NG: 'Nigeria',
            NU: 'Niue',
            NF: 'Norfolk Island',
            KP: 'North Korea',
            MP: 'Northern Mariana Islands',
            NO: 'Norway',
            OM: 'Oman',
            PK: 'Pakistan',
            PW: 'Palau',
            PS: 'Palestine',
            PA: 'Panama',
            PG: 'Papua New Guinea',
            PY: 'Paraguay',
            PE: 'Peru',
            PH: 'Philippines',
            PN: 'Pitcairn Islands',
            PL: 'Poland',
            PT: 'Portugal',
            PR: 'Puerto Rico',
            QA: 'Qatar',
            RE: 'Reunion',
            RO: 'Romania',
            RU: 'Russia',
            RW: 'Rwanda',
            SH: 'Saint Helena',
            KN: 'Saint Kitts and Nevis',
            LC: 'Saint Lucia',
            PM: 'Saint Pierre and Miquelon',
            VC: 'Saint Vincent and the Grenadines',
            WS: 'Samoa',
            SM: 'San Marino',
            ST: 'São Tomé and Príncipe',
            SA: 'Saudi Arabia',
            SN: 'Senegal',
            RS: 'Serbia',
            SC: 'Seychelles',
            SL: 'Sierra Leone',
            SG: 'Singapore',
            SX: 'Sint Maarten',
            SK: 'Slovakia',
            SI: 'Slovenia',
            SB: 'Solomon Islands',
            SO: 'Somalia',
            ZA: 'South Africa',
            GS: 'South Georgia and the South Sandwich Islands',
            KR: 'South Korea',
            SS: 'South Sudan',
            ES: 'Spain',
            LK: 'Sri Lanka',
            SD: 'Sudan',
            SR: 'Suriname',
            SJ: 'Svalbard and Jan Mayen',
            SZ: 'Eswatini',
            SE: 'Sweden',
            CH: 'Switzerland',
            SY: 'Syria',
            TW: 'Taiwan',
            TJ: 'Tajikistan',
            TZ: 'Tanzania',
            TH: 'Thailand',
            TL: 'Timor-Leste',
            TG: 'Togo',
            TK: 'Tokelau',
            TO: 'Tonga',
            TT: 'Trinidad and Tobago',
            TN: 'Tunisia',
            TR: 'Turkey',
            TM: 'Turkmenistan',
            TC: 'Turks and Caicos Islands',
            TV: 'Tuvalu',
            UG: 'Uganda',
            UA: 'Ukraine',
            AE: 'United Arab Emirates',
            GB: 'United Kingdom',
            US: 'United States',
            UM: 'United States Minor Outlying Islands',
            VI: 'United States Virgin Islands',
            UY: 'Uruguay',
            UZ: 'Uzbekistan',
            VU: 'Vanuatu',
            VA: 'Vatican City',
            VE: 'Venezuela',
            VN: 'Vietnam',
            WF: 'Wallis and Futuna',
            EH: 'Western Sahara',
            YE: 'Yemen',
            ZM: 'Zambia',
            ZW: 'Zimbabwe'
        }

        return countries[countryCode];
    }
}