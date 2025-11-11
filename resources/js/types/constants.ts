/**
 * Application Constants
 * Contains constant values used throughout the application
 */

/**
 * Municipal offices options for the select dropdown
 */
export const MUNICIPAL_OFFICES = [
    { value: "MO - Mayor's Office", label: "MO - Mayor's Office" },
    { value: 'OMA - Office of the Municipal Administrator', label: 'OMA - Office of the Municipal Administrator' },
    { value: "VMO/SBO - Vice Mayor's Office / Sangguniang Bayan Office", label: "VMO/SBO - Vice Mayor's Office / Sangguniang Bayan Office" },
    { value: 'BAC - Bids and Awards Committee Office', label: 'BAC - Bids and Awards Committee Office' },
    { value: "MTO - Municipal Treasurer's Office", label: "MTO - Municipal Treasurer's Office" },
    { value: "MACCO - Municipal Accountant's Office", label: "MACCO - Municipal Accountant's Office" },
    { value: 'MBO - Municipal Budget Office', label: 'MBO - Municipal Budget Office' },
    { value: 'GSO - General Services Office', label: 'GSO - General Services Office' },
    { value: 'MPDO - Municipal Planning and Development Office', label: 'MPDO - Municipal Planning and Development Office' },
    { value: 'MEO - Municipal Engineering Office', label: 'MEO - Municipal Engineering Office' },
    { value: 'HRMO - Human Resource Management Office', label: 'HRMO - Human Resource Management Office' },
    { value: 'MSWDO - Municipal Social Welfare and Development Office', label: 'MSWDO - Municipal Social Welfare and Development Office' },
    { value: 'MHO - Municipal Health Office', label: 'MHO - Municipal Health Office' },
    { value: 'MAGO - Municipal Agriculture Office', label: 'MAGO - Municipal Agriculture Office' },
    {
        value: 'MDDRMO - Municipal Disaster Risk Reduction and Management Office',
        label: 'MDDRMO - Municipal Disaster Risk Reduction and Management Office',
    },
    { value: 'MENRO - Municipal Environment and Natural Resources Office', label: 'MENRO - Municipal Environment and Natural Resources Office' },
    { value: 'BPLO - Business Permits and Licensing Office', label: 'BPLO - Business Permits and Licensing Office' },
    { value: "MCRO - Municipal Civil Registrar's Office", label: "MCRO - Municipal Civil Registrar's Office" },
    { value: "MASSO - Municipal Assessor's Office", label: "MASSO - Municipal Assessor's Office" },
    { value: 'COA - Commission on Audit', label: 'COA - Commission on Audit' },
    { value: 'MARKET - Market Administration Office', label: 'MARKET - Market Administration Office' },
    { value: 'TOURISM - Tourism Office', label: 'TOURISM - Tourism Office' },
    { value: 'PESO - Public Employment Service Office', label: 'PESO - Public Employment Service Office' },
    { value: 'YDS - Youth Development Services', label: 'YDS - Youth Development Services' },
    { value: 'PDAO - Persons with Disability Affairs Office', label: 'PDAO - Persons with Disability Affairs Office' },
    { value: 'OSCA - Office of the Senior Citizens Affairs', label: 'OSCA - Office of the Senior Citizens Affairs' },
    { value: 'COOPERATIVES - Cooperatives Development Office', label: 'COOPERATIVES - Cooperatives Development Office' },
    {
        value: 'KALAHI - Kapit-Bisig Laban sa Kahirapan – Comprehensive and Integrated Delivery of Social Services',
        label: 'KALAHI - Kapit-Bisig Laban sa Kahirapan – Comprehensive and Integrated Delivery of Social Services',
    },
    { value: 'GIST - Gloria Institute of Science and Technology Office', label: 'GIST - Gloria Institute of Science and Technology Office' },
    { value: 'Zoning - Zoning Office', label: 'Zoning - Zoning Office' },
    { value: 'SLAUGHTER - Slaughterhouse Office', label: 'SLAUGHTER - Slaughterhouse Office' },
    { value: 'BAMBOO - Bamboo Plantation', label: 'BAMBOO - Bamboo Plantation' },
] as const;

export type MunicipalOffice = typeof MUNICIPAL_OFFICES[number]['value'];
