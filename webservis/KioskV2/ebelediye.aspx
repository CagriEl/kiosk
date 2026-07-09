<%@ Page Title="" Language="C#" MasterPageFile="~/kiosk.Master" AutoEventWireup="true" CodeBehind="ebelediye.aspx.cs" Inherits="kiosk.ebelediye" %>
<asp:Content ID="Content1" ContentPlaceHolderID="head" runat="server"></asp:Content>
<asp:Content ID="Content2" ContentPlaceHolderID="ContentPlaceHolder1" runat="server">
    
    <div style="text-align:center;">
        <iframe id="eBelFrame" class="innerFrame" runat="server"></iframe>
    </div>

</asp:Content>
