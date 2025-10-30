#!/bin/bash

# SEO Verification Script for ProcuChain
# This script checks if all SEO improvements are properly implemented

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  ProcuChain SEO Implementation Verification"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Counters
passed=0
failed=0

check_file() {
    if [ -f "$1" ]; then
        echo -e "${GREEN}✓${NC} Found: $1"
        ((passed++))
    else
        echo -e "${RED}✗${NC} Missing: $1"
        ((failed++))
    fi
}

check_content() {
    if grep -q "$2" "$1" 2>/dev/null; then
        echo -e "${GREEN}✓${NC} $3"
        ((passed++))
    else
        echo -e "${RED}✗${NC} $3"
        ((failed++))
    fi
}

echo "1. Checking Core SEO Files..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
check_file "public/sitemap.xml"
check_file "public/manifest.json"
check_file "public/robots.txt"
echo ""

echo "2. Checking SEO Components..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
check_file "resources/js/components/seo.tsx"
check_file "resources/js/lib/seo-utils.ts"
check_file "docs/SEO_IMPROVEMENTS.md"
echo ""

echo "3. Checking Base Template..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
check_content "resources/views/app.blade.php" "manifest.json" "Web manifest linked"
check_content "resources/views/app.blade.php" "canonical" "Canonical URL present"
check_content "resources/views/app.blade.php" "theme-color" "Theme color meta tag"
check_content "resources/views/app.blade.php" "robots" "Robots meta tag"
echo ""

echo "4. Checking robots.txt..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
check_content "public/robots.txt" "Sitemap:" "Sitemap reference in robots.txt"
check_content "public/robots.txt" "Disallow: /admin" "Admin blocked from crawling"
check_content "public/robots.txt" "Disallow: /api" "API blocked from crawling"
echo ""

echo "5. Checking Open Graph Tags..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
check_content "resources/js/pages/home.tsx" "og:title" "Home page has OG title"
check_content "resources/js/pages/about.tsx" "og:description" "About page has OG description"
check_content "resources/js/pages/contact.tsx" "og:image" "Contact page has OG image"
check_content "resources/js/pages/team.tsx" "og:type" "Team page has OG type"
echo ""

echo "6. Checking Twitter Cards..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
check_content "resources/js/pages/home.tsx" "twitter:card" "Home page has Twitter card"
check_content "resources/js/pages/about.tsx" "twitter:title" "About page has Twitter title"
check_content "resources/js/pages/contact.tsx" "twitter:description" "Contact page has Twitter description"
check_content "resources/js/pages/team.tsx" "twitter:image" "Team page has Twitter image"
echo ""

echo "7. Checking Meta Descriptions..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
check_content "resources/js/pages/home.tsx" "name=\"description\"" "Home page has meta description"
check_content "resources/js/pages/about.tsx" "name=\"description\"" "About page has meta description"
check_content "resources/js/pages/contact.tsx" "name=\"description\"" "Contact page has meta description"
check_content "resources/js/pages/team.tsx" "name=\"description\"" "Team page has meta description"
echo ""

echo "8. Checking Keywords..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
check_content "resources/js/pages/home.tsx" "name=\"keywords\"" "Home page has keywords"
check_content "resources/js/pages/about.tsx" "name=\"keywords\"" "About page has keywords"
check_content "resources/js/pages/contact.tsx" "name=\"keywords\"" "Contact page has keywords"
check_content "resources/js/pages/team.tsx" "name=\"keywords\"" "Team page has keywords"
echo ""

echo "9. Checking Structured Data..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
check_content "resources/js/pages/home.tsx" "application/ld+json" "JSON-LD structured data present"
check_content "resources/js/pages/home.tsx" "SoftwareApplication" "Software Application schema"
check_content "resources/js/pages/home.tsx" "featureList" "Feature list in schema"
echo ""

echo "10. Checking .htaccess Optimizations..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
check_content "public/.htaccess" "mod_deflate" "GZIP compression enabled"
check_content "public/.htaccess" "mod_expires" "Browser caching configured"
echo ""

echo "11. Checking Auth Page Protection..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
check_content "resources/js/pages/auth/login.tsx" "noindex" "Login page has noindex"
echo ""

# Final Results
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  Test Results"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "${GREEN}Passed: $passed${NC}"
echo -e "${RED}Failed: $failed${NC}"

total=$((passed + failed))
percentage=$((passed * 100 / total))

echo ""
echo -e "Score: ${YELLOW}${percentage}%${NC}"
echo ""

if [ $failed -eq 0 ]; then
    echo -e "${GREEN}✓ All SEO checks passed! Your site is well optimized.${NC}"
    exit 0
else
    echo -e "${YELLOW}⚠ Some SEO improvements are missing. Review the failed checks above.${NC}"
    exit 1
fi
