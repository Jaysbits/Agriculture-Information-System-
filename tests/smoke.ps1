param(
    [string]$BaseUrl  = 'http://localhost:8000',
    [string]$Username = 'admin',
    [string]$Password = 'admin123'
)

$ErrorActionPreference = 'Stop'
$base = $BaseUrl.TrimEnd('/')
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

$login = Invoke-WebRequest -Uri "$base/login.php" -WebSession $session -UseBasicParsing
$token = [regex]::Match($login.Content, 'name="csrf_token" value="([a-f0-9]+)"').Groups[1].Value
$body = @{ csrf_token = $token; username = $Username; password = $Password }
$after = Invoke-WebRequest -Uri "$base/login.php" -Method Post -Body $body -WebSession $session -UseBasicParsing
"LOGIN -> $($after.BaseResponse.RequestMessage.RequestUri.AbsoluteUri)"
"LOGIN contains welcome: $($after.Content -match 'Welcome back')"
"LOGIN contains dashboard: $($after.Content -match 'Recent planting records')"

$pages = @(
    '/index.php',
    '/farmers/index.php',
    '/farmers/form.php',
    '/farmers/view.php?id=1',
    '/farms/index.php',
    '/farms/form.php',
    '/farms/view.php?id=1',
    '/crops/index.php',
    '/crops/form.php',
    '/crops/view.php?id=1',
    '/plantings/index.php',
    '/plantings/form.php?farm_id=1',
    '/plantings/view.php?id=1',
    '/harvests/index.php',
    '/harvests/form.php?planting_id=1',
    '/harvests/view.php?id=1',
    '/reports.php',
    '/reports.php?report=farm_crops',
    '/reports.php?report=planting_status',
    '/reports.php?report=harvests',
    '/reports.php?report=harvest_totals',
    '/reports.php?report=harvest_value',
    '/reports.php?report=harvest_totals&export=csv',
    '/users/index.php',
    '/users/form.php',
    '/users/form.php?id=2',
    '/lookups.php',
    '/lookups.php?table=harvest_remark'
)

foreach ($page in $pages) {
    try {
        $r = Invoke-WebRequest -Uri "$base$page" -WebSession $session -UseBasicParsing
        $err = ''
        if ($r.Content -match 'Request failed|Fatal error|Warning:|Uncaught') { $err = ' <-- PHP ERROR TEXT' }
        "{0,5}  {1}{2}" -f $r.StatusCode, $page, $err
    } catch {
        "ERROR  $page -> $($_.Exception.Message)"
    }
}

# Logout via POST (the 302 back to login is the expected result)
$logout = Invoke-WebRequest -Uri "$base/logout.php" -Method Post -Body @{ csrf_token = $token } -WebSession $session -UseBasicParsing
"LOGOUT -> $($logout.StatusCode)"
