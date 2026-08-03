export const countries = [
    { iso: "BR", name: "Brasil", dial: "55", flag: "🇧🇷" },
    { iso: "PT", name: "Portugal", dial: "351", flag: "🇵🇹" },
    { iso: "US", name: "Estados Unidos", dial: "1", flag: "🇺🇸" },
    { iso: "AR", name: "Argentina", dial: "54", flag: "🇦🇷" },
    { iso: "AO", name: "Angola", dial: "244", flag: "🇦🇴" },
    { iso: "BO", name: "Bolívia", dial: "591", flag: "🇧🇴" },
    { iso: "CA", name: "Canadá", dial: "1", flag: "🇨🇦" },
    { iso: "CL", name: "Chile", dial: "56", flag: "🇨🇱" },
    { iso: "CO", name: "Colômbia", dial: "57", flag: "🇨🇴" },
    { iso: "CR", name: "Costa Rica", dial: "506", flag: "🇨🇷" },
    { iso: "CU", name: "Cuba", dial: "53", flag: "🇨🇺" },
    { iso: "EC", name: "Equador", dial: "593", flag: "🇪🇨" },
    { iso: "ES", name: "Espanha", dial: "34", flag: "🇪🇸" },
    { iso: "FR", name: "França", dial: "33", flag: "🇫🇷" },
    { iso: "DE", name: "Alemanha", dial: "49", flag: "🇩🇪" },
    { iso: "GB", name: "Reino Unido", dial: "44", flag: "🇬🇧" },
    { iso: "IT", name: "Itália", dial: "39", flag: "🇮🇹" },
    { iso: "JP", name: "Japão", dial: "81", flag: "🇯🇵" },
    { iso: "MX", name: "México", dial: "52", flag: "🇲🇽" },
    { iso: "MZ", name: "Moçambique", dial: "258", flag: "🇲🇿" },
    { iso: "PY", name: "Paraguai", dial: "595", flag: "🇵🇾" },
    { iso: "PE", name: "Peru", dial: "51", flag: "🇵🇪" },
    { iso: "UY", name: "Uruguai", dial: "598", flag: "🇺🇾" },
    { iso: "VE", name: "Venezuela", dial: "58", flag: "🇻🇪" },
    { iso: "ZA", name: "África do Sul", dial: "27", flag: "🇿🇦" },
    { iso: "AU", name: "Austrália", dial: "61", flag: "🇦🇺" },
    { iso: "CH", name: "Suíça", dial: "41", flag: "🇨🇭" },
    { iso: "NL", name: "Países Baixos", dial: "31", flag: "🇳🇱" },
    { iso: "IE", name: "Irlanda", dial: "353", flag: "🇮🇪" },
    { iso: "CV", name: "Cabo Verde", dial: "238", flag: "🇨🇻" },
];

export const defaultCountry = countries[0];

export const countryOptions = countries.map((country) => ({
    value: country.iso,
    label: `${country.flag} ${country.iso} +${country.dial}`,
}));

export function countryFor(iso) {
    return countries.find((country) => country.iso === iso) ?? defaultCountry;
}
