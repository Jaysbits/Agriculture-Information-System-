param(
    [string]$BaseUrl   = 'http://localhost:8000',
    [string]$AdminUser = 'admin',
    [string]$AdminPass = 'admin123',
    [string]$StaffUser = 'staff',
    [string]$StaffPass = 'staff123',
    [string]$PsqlPath  = 'psql',
    [string]$DbHost    = 'localhost',
    [int]   $DbPort    = 5432,
    [string]$DbName    = 'postgres',
    [string]$DbUser    = 'postgres',
    [string]$DbPassword = '',
    [string]$DbSslMode = 'prefer'
)

$ErrorActionPreference = 'Stop'
$base = $BaseUrl.TrimEnd('/')
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

# psql is only used to verify what actually reached the database.
$env:PGPASSWORD = $DbPassword
$env:PGSSLMODE  = $DbSslMode

function Sql($q) {
    & $PsqlPath -h $DbHost -p $DbPort -U $DbUser -d $DbName -t -A -c $q
}
function Get-Page($path) {
    Invoke-WebRequest -Uri "$base$path" -WebSession $session -UseBasicParsing
}
function Post-Page($path, $body) {
    Invoke-WebRequest -Uri "$base$path" -Method Post -Body $body -WebSession $session -UseBasicParsing
}
function Check($name, $ok, $detail = '') {
    if ($ok) { "PASS  $name" } else { "FAIL  $name $detail" }
}

$login = Get-Page '/login.php'
$token = [regex]::Match($login.Content, 'name="csrf_token" value="([a-f0-9]+)"').Groups[1].Value
Post-Page '/login.php' @{ csrf_token = $token; username = $AdminUser; password = $AdminPass } | Out-Null

# ---------- validation ----------
$r = Post-Page '/farmers/form.php' @{ csrf_token=$token; first_name=''; last_name=''; contact_number='abc'; address=''; date_registered='not-a-date' }
Check 'farmer validation rejects empty/invalid fields' (($r.Content -match 'First name is required') -and ($r.Content -match 'valid date'))

# ---------- create farmer ----------
$r = Post-Page '/farmers/form.php' @{ csrf_token=$token; first_name='Test'; last_name='Farmer'; contact_number='09990001111'; address='Barangay Test, Cebu City'; date_registered='2026-01-05' }
$farmerId = (Sql "select farmer_id from farmer where contact_number='09990001111'").Trim()
Check 'create farmer' ($farmerId -ne '' -and $r.Content -match 'registered successfully') "(id=$farmerId)"

$r = Post-Page '/farmers/form.php' @{ csrf_token=$token; first_name='Other'; last_name='Person'; contact_number='09990001111'; address='Elsewhere'; date_registered='2026-01-05' }
Check 'duplicate contact rejected' ($r.Content -match 'already used by another record')

# ---------- create farm ----------
$r = Post-Page '/farms/form.php' @{ csrf_token=$token; farmer_id=$farmerId; soil_type_id='1'; irrigation_id='1'; farm_name='Test Plot'; location='Cebu City'; area_hectares='1.00' }
$farmId = (Sql "select farm_id from farm where farm_name='Test Plot' and farmer_id=$farmerId").Trim()
Check 'create farm' ($farmId -ne '' -and $r.Content -match 'registered successfully') "(id=$farmId)"

$r = Post-Page '/farms/form.php' @{ csrf_token=$token; farmer_id=$farmerId; soil_type_id='1'; irrigation_id='1'; farm_name='Test Plot'; location='Cebu City'; area_hectares='1.00' }
Check 'duplicate farm name per farmer rejected' ($r.Content -match 'already has a farm with that name')

# ---------- create crop ----------
$r = Post-Page '/crops/form.php' @{ csrf_token=$token; category_id='2'; season_id='3'; crop_name='Ampalaya'; variety='Strain A'; growing_period_days='70' }
$cropId = (Sql "select crop_id from crop where crop_name='Ampalaya'").Trim()
Check 'create crop' ($cropId -ne '' -and $r.Content -match 'added successfully') "(id=$cropId)"

# ---------- planting ----------
$r = Post-Page '/plantings/form.php' @{ csrf_token=$token; farm_id=$farmId; crop_id=$cropId; status_id='2'; planting_date='2026-02-01'; area_planted='2.00'; seed_quantity='2'; expected_harvest_date='2026-04-01' }
Check 'planting larger than farm rejected' ($r.Content -match 'cannot be larger than the farm size')

$r = Post-Page '/plantings/form.php' @{ csrf_token=$token; farm_id=$farmId; crop_id=$cropId; status_id='2'; planting_date='2026-02-01'; area_planted='0.50'; seed_quantity='2'; expected_harvest_date='2026-01-01' }
Check 'expected harvest before planting rejected' ($r.Content -match 'cannot be earlier than the planting date')

$r = Post-Page '/plantings/form.php' @{ csrf_token=$token; farm_id=$farmId; crop_id=$cropId; status_id='2'; planting_date='2026-02-01'; area_planted='0.50'; seed_quantity='2'; expected_harvest_date='2026-04-01' }
$plantingId = (Sql "select planting_id from planting where farm_id=$farmId").Trim()
Check 'create planting' ($plantingId -ne '' -and $r.Content -match 'added successfully') "(id=$plantingId)"

# ---------- harvest ----------
$r = Post-Page '/harvests/form.php' @{ csrf_token=$token; planting_id=$plantingId; grade_id='1'; remark_id='1'; harvest_date='2026-01-01'; quantity_kg='100'; selling_price_per_kg='10' }
Check 'harvest before planting rejected' ($r.Content -match 'cannot be earlier than the planting date')

$r = Post-Page '/harvests/form.php' @{ csrf_token=$token; planting_id=$plantingId; grade_id='1'; remark_id='1'; harvest_date='2026-04-10'; quantity_kg='100'; selling_price_per_kg='10' }
$harvestId = (Sql "select harvest_id from harvest where planting_id=$plantingId").Trim()
Check 'create harvest' ($harvestId -ne '' -and $r.Content -match 'added successfully') "(id=$harvestId)"

$value = (Sql "select estimated_value from v_harvest_detail where harvest_id=$harvestId").Trim()
Check 'estimated value computed (100 x 10 = 1000)' ($value -eq '1000.00') "(got $value)"

# ---------- referential integrity ----------
$r = Post-Page "/farmers/delete.php?id=$farmerId" @{ csrf_token=$token }
$stillThere = (Sql "select count(*) from farmer where farmer_id=$farmerId").Trim()
Check 'farmer with farms cannot be deleted' ($stillThere -eq '1' -and $r.Content -match 'still used by other records')

$r = Post-Page "/plantings/delete.php?id=$plantingId" @{ csrf_token=$token }
$stillThere = (Sql "select count(*) from planting where planting_id=$plantingId").Trim()
Check 'planting with harvest cannot be deleted' ($stillThere -eq '1')

# ---------- update ----------
$r = Post-Page '/farmers/form.php' @{ csrf_token=$token; id=$farmerId; first_name='Test'; last_name='Farmer Updated'; contact_number='09990001111'; address='Barangay Test, Cebu City'; date_registered='2026-01-05' }
$updated = (Sql "select last_name from farmer where farmer_id=$farmerId").Trim()
Check 'update farmer' ($updated -eq 'Farmer Updated' -and $r.Content -match 'updated successfully')

# ---------- deletes in dependency order ----------
Post-Page "/harvests/delete.php?id=$harvestId" @{ csrf_token=$token } | Out-Null
Post-Page "/plantings/delete.php?id=$plantingId" @{ csrf_token=$token } | Out-Null
Post-Page "/farms/delete.php?id=$farmId" @{ csrf_token=$token } | Out-Null
$r = Post-Page "/farmers/delete.php?id=$farmerId" @{ csrf_token=$token }
$gone = (Sql "select count(*) from farmer where farmer_id=$farmerId").Trim()
Check 'delete chain removes all test records' ($gone -eq '0' -and $r.Content -match 'deleted successfully')

Sql "delete from crop where crop_id=$cropId" | Out-Null

# ---------- lookups ----------
$r = Post-Page '/lookups.php?table=soil_type' @{ csrf_token=$token; action='add'; name='Test Soil' }
$soilId = (Sql "select soil_type_id from soil_type where soil_type_name='Test Soil'").Trim()
Check 'lookup add' ($soilId -ne '' -and $r.Content -match 'Lookup value added') "(id=$soilId)"

$r = Post-Page '/lookups.php?table=soil_type' @{ csrf_token=$token; action='update'; record_id=$soilId; name='Test Soil Renamed' }
$renamed = (Sql "select soil_type_name from soil_type where soil_type_id=$soilId").Trim()
Check 'lookup update' ($renamed -eq 'Test Soil Renamed')

$r = Post-Page "/lookups.php?table=soil_type&delete=$soilId" @{ csrf_token=$token }
$gone = (Sql "select count(*) from soil_type where soil_type_id=$soilId").Trim()
Check 'lookup delete' ($gone -eq '0' -and $r.Content -match 'Lookup value deleted')

$r = Post-Page '/lookups.php?table=soil_type&delete=1' @{ csrf_token=$token }
$used = (Sql "select count(*) from soil_type where soil_type_id=1").Trim()
Check 'lookup value in use cannot be deleted' ($used -eq '1' -and $r.Content -match 'still used by other records')

# ---------- reports ----------
$csv = Get-Page '/reports.php?report=harvest_totals&from=2025-01-01&to=2026-12-31&export=csv'
Check 'CSV report export' ($csv.Headers['Content-Type'] -match 'text/csv' -and $csv.Content -match 'Total harvested quantity by crop')

$r = Get-Page '/reports.php?report=harvest_value&from=2025-01-01&to=2026-12-31'
Check 'harvest value report renders total' ($r.Content -match 'Estimated harvest value')

# ---------- staff restrictions ----------
$staffSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$li = Invoke-WebRequest -Uri "$base/login.php" -WebSession $staffSession -UseBasicParsing
$stoken = [regex]::Match($li.Content, 'name="csrf_token" value="([a-f0-9]+)"').Groups[1].Value
Invoke-WebRequest -Uri "$base/login.php" -Method Post -Body @{ csrf_token=$stoken; username=$StaffUser; password=$StaffPass } -WebSession $staffSession -UseBasicParsing | Out-Null

$r = Invoke-WebRequest -Uri "$base/users/index.php" -WebSession $staffSession -UseBasicParsing
Check 'staff blocked from user accounts' ($r.Content -match 'Administrator access is required')

$r = Invoke-WebRequest -Uri "$base/farmers/index.php" -WebSession $staffSession -UseBasicParsing
Check 'staff can read farmers but sees no delete buttons' ($r.Content -match 'Farmers' -and $r.Content -notmatch 'data-delete-url')

$before = (Sql "select count(*) from farmer where farmer_id=1").Trim()
$r = Invoke-WebRequest -Uri "$base/farmers/delete.php?id=1" -Method Post -Body @{ csrf_token=$stoken } -WebSession $staffSession -UseBasicParsing
$after = (Sql "select count(*) from farmer where farmer_id=1").Trim()
Check 'staff cannot delete (delete endpoint is admin-only)' ($before -eq '1' -and $after -eq '1')

# ---------- login failure ----------
$bad = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$l = Invoke-WebRequest -Uri "$base/login.php" -WebSession $bad -UseBasicParsing
$bt = [regex]::Match($l.Content, 'name="csrf_token" value="([a-f0-9]+)"').Groups[1].Value
$r = Invoke-WebRequest -Uri "$base/login.php" -Method Post -Body @{ csrf_token=$bt; username=$AdminUser; password='wrong-pass' } -WebSession $bad -UseBasicParsing
Check 'wrong password rejected' ($r.Content -match 'Invalid username or password')
