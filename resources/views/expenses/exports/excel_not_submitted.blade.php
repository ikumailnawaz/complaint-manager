{!! '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' !!}
{!! '<' . '?mso-application progid="Excel.Sheet"?' . '>' !!}
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Author>Bank Complaint Manager</Author>
  <Company>Bank Complaint Support Services</Company>
  <Created>{{ now()->toIso8601String() }}</Created>
 </DocumentProperties>
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#1E293B"/>
  </Style>
  <Style ss:ID="HeaderMaster">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#0F172A" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#334155"/>
   </Borders>
  </Style>
  <Style ss:ID="HeaderSummary">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#047857" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#065F46"/>
   </Borders>
  </Style>
  <Style ss:ID="CellLeft">
   <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
  </Style>
  <Style ss:ID="CellCenter">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  </Style>
  <Style ss:ID="CellInteger">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <NumberFormat ss:Format="#,##0"/>
  </Style>
  <Style ss:ID="StatusNotSubmitted">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#9A3412"/>
   <Interior ss:Color="#FFEDD5" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="TotalLabel">
   <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#0F172A"/>
   <Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>
    <Border ss:Position="Bottom" ss:LineStyle="Double" ss:Weight="2" ss:Color="#475569"/>
   </Borders>
  </Style>
  <Style ss:ID="TotalInteger">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#0F172A"/>
   <Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/>
   <NumberFormat ss:Format="#,##0"/>
   <Borders>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#94A3B8"/>
    <Border ss:Position="Bottom" ss:LineStyle="Double" ss:Weight="2" ss:Color="#475569"/>
   </Borders>
  </Style>
 </Styles>

 <!-- SHEET 1: MASTER TICKETS PENDING CLAIMS -->
 <Worksheet ss:Name="Master Sheet">
  <Table>
   <Column ss:Width="85"/>  <!-- Ticket No -->
   <Column ss:Width="160"/> <!-- Bank Name -->
   <Column ss:Width="140"/> <!-- Branch / Location -->
   <Column ss:Width="130"/> <!-- Assigned Engineer -->
   <Column ss:Width="120"/> <!-- Machine Type -->
   <Column ss:Width="90"/>  <!-- Urgency -->
   <Column ss:Width="100"/> <!-- Ticket Status -->
   <Column ss:Width="120"/> <!-- Claim Status -->
   <Column ss:Width="120"/> <!-- Settlement Status -->
   <Column ss:Width="120"/> <!-- Created Date -->

   <Row ss:Height="26">
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Ticket No</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Bank Name</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Branch / Location</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Assigned Engineer</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Machine Type</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Urgency</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Ticket Status</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Claim Status</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Settlement Status</Data></Cell>
    <Cell ss:StyleID="HeaderMaster"><Data ss:Type="String">Created Date</Data></Cell>
   </Row>

   @foreach($tickets as $t)
   <Row ss:Height="20">
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">{{ $t->ticket_no }}</Data></Cell>
    <Cell ss:StyleID="CellLeft"><Data ss:Type="String">{{ $t->bank_name }}</Data></Cell>
    <Cell ss:StyleID="CellLeft"><Data ss:Type="String">{{ $t->branch_location ?? $t->branch_name ?? 'N/A' }}</Data></Cell>
    <Cell ss:StyleID="CellLeft"><Data ss:Type="String">{{ $t->assignedEngineer?->name ?? 'Unassigned' }}</Data></Cell>
    <Cell ss:StyleID="CellLeft"><Data ss:Type="String">{{ $t->machine_type ?? 'N/A' }}</Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">{{ strtoupper($t->urgency ?? 'NORMAL') }}</Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">{{ strtoupper(str_replace('_', ' ', $t->status)) }}</Data></Cell>
    <Cell ss:StyleID="StatusNotSubmitted"><Data ss:Type="String">NOT SUBMITTED</Data></Cell>
    <Cell ss:StyleID="StatusNotSubmitted"><Data ss:Type="String">UNPAID &amp; UNSETTLED</Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">{{ $t->created_at->format('Y-m-d H:i') }}</Data></Cell>
   </Row>
   @endforeach

  </Table>
 </Worksheet>

 <!-- SHEET 2: ENGINEER SUMMARY -->
 <Worksheet ss:Name="Engineer Summary">
  <Table>
   <Column ss:Width="160"/> <!-- Engineer Name -->
   <Column ss:Width="120"/> <!-- Pending Exp No's -->
   <Column ss:Width="180"/> <!-- Total AI Estimated Distance (KM) -->
   <Column ss:Width="160"/> <!-- Average Tour Cost (PKR) -->
   <Column ss:Width="160"/> <!-- Total Tour Cost (PKR) -->

   <Row ss:Height="26">
    <Cell ss:StyleID="HeaderSummary"><Data ss:Type="String">Engineer Name</Data></Cell>
    <Cell ss:StyleID="HeaderSummary"><Data ss:Type="String">Pending Claims (Exp No's)</Data></Cell>
    <Cell ss:StyleID="HeaderSummary"><Data ss:Type="String">Total AI Estimated Distance (KM)</Data></Cell>
    <Cell ss:StyleID="HeaderSummary"><Data ss:Type="String">Average Tour Cost (PKR)</Data></Cell>
    <Cell ss:StyleID="HeaderSummary"><Data ss:Type="String">Total Tour Cost (PKR)</Data></Cell>
   </Row>

   @foreach($engineerSummaries as $s)
   <Row ss:Height="20">
    <Cell ss:StyleID="CellLeft"><Data ss:Type="String">{{ $s['engineer_name'] }}</Data></Cell>
    <Cell ss:StyleID="CellInteger"><Data ss:Type="Number">{{ $s['exp_count'] }}</Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">Pending Claim</Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">Pending Claim</Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">Pending Claim</Data></Cell>
   </Row>
   @endforeach

   <Row ss:Height="24">
    <Cell ss:StyleID="TotalLabel"><Data ss:Type="String">TOTAL / ALL ENGINEERS</Data></Cell>
    <Cell ss:StyleID="TotalInteger"><Data ss:Type="Number">{{ $totalCount }}</Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">-</Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">-</Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">-</Data></Cell>
   </Row>

  </Table>
 </Worksheet>
</Workbook>
