<?php

namespace Query;

class MinecraftQuery {
	const STATISTIC = 0x00;
	const HANDSHAKE = 0x09;
	private $Socket;
	private $Players;
	private $Info;
	public function Connect( $Ip, $Port = 25565, $Timeout = 3, $ResolveSRV = true )
	{
		if( !is_int( $Timeout ) || $Timeout < 0 )
		{
			throw new \InvalidArgumentException("Timeout must be an integer.");
			return false;
		}
		if( $ResolveSRV )
		{
			$result = $this->ResolveSRV( $Ip, $Port );
		}
		$this->Socket = @FSockOpen("udp://" . $Ip, (int)$Port, $ErrNo, $ErrStr, $Timeout );
		if( $ErrNo || !$this->Socket){
			return false;
		}
		Stream_Set_Timeout( $this->Socket, $Timeout );
		Stream_Set_Blocking( $this->Socket, true );
		try
		{
			$Challenge = $this->GetChallenge( );
			$this->GetStatus( $Challenge );
		}
		// We catch this because we want to close the socket, not very elegant
		catch( MinecraftQueryException $e )
		{
			FClose( $this->Socket );
		}
		FClose( $this->Socket );
		return true;
	}
public functiom isOnline()
{
 $info = $this->GetInfo();
 if(!empty($info["HostPort"])){
  return true;
 }
return false;
}
	public function GetInfo( )
	{
		return isset( $this->Info ) ? $this->Info : false;
	}
	public function GetPlayers( )
	{
		return isset( $this->Players ) ? $this->Players : false;
	}
	private function GetChallenge( )
	{
		$Data = $this->WriteData( self :: HANDSHAKE );
		if( $Data === false )
		{
		}
		return Pack( "N", $Data );
	}
	private function GetStatus( $Challenge )
	{
		$Data = $this->WriteData( self :: STATISTIC, $Challenge . Pack( "c*", 0x00, 0x00, 0x00, 0x00 ) );
		if( !$Data )
		{
		}
		$Last = "";
		$Info = Array( );
		$Data    = SubStr( $Data, 11 ); // splitnum + 2 int
		$Data    = Explode( "\x00\x00\x01player_\x00\x00", $Data );
		if( Count( $Data ) !== 2 )
		{
		}
		if(!isset($Data[1])) return false;
		$Players = SubStr( $Data[ 1 ], 0, -2 );
		$Data    = Explode( "\x00", $Data[ 0 ] );
		// Array with known keys in order to validate the result
		// It can happen that server sends custom strings containing bad things (who can know!)
		$Keys = Array(
			"hostname"   => "HostName",
			"gametype"   => "GameType",
			"version"    => "Version",
			"plugins"    => "Plugins",
			"map"        => "Map",
			"numplayers" => "Players",
			"maxplayers" => "MaxPlayers",
			"hostport"   => "HostPort",
			"hostip"     => "HostIp",
			"game_id"    => "GameName"
		);
		foreach( $Data as $Key => $Value )
		{
			if( ~$Key & 1 )
			{
				if( !Array_Key_Exists( $Value, $Keys ) )
				{
					$Last = false;
					continue;
				}
				$Last = $Keys[ $Value ];
				$Info[ $Last ] = "";
			}
			else if( $Last != false )
			{
				$Info[ $Last ] = mb_convert_encoding( $Value, "UTF-8" );
			}
		}
		// Ints
		$Info[ "Players" ]    = IntVal( $Info[ "Players" ] );
		$Info[ "MaxPlayers" ] = IntVal( $Info[ "MaxPlayers" ] );
		$Info[ "HostPort" ]   = IntVal( $Info[ "HostPort" ] );
		// Parse "plugins", if any
		if( $Info[ "Plugins" ] )
		{
			$Data = Explode( ": ", $Info[ "Plugins" ], 2 );
			$Info[ "RawPlugins" ] = $Info[ "Plugins" ];
			$Info[ "Software" ]   = $Data[ 0 ];
			if( Count( $Data ) == 2 )
			{
				$Info[ "Plugins" ] = Explode( "; ", $Data[ 1 ] );
			}
		}
		else
		{
			$Info[ "Software" ] = "Vanilla";
		}
		$this->Info = $Info;
		if( empty( $Players ) )
		{
			$this->Players = null;
		}
		else
		{
			$this->Players = Explode( "\x00", $Players );
		}
	}
	private function WriteData( $Command, $Append = "" )
	{
		$Command = Pack( "c*", 0xFE, 0xFD, $Command, 0x01, 0x02, 0x03, 0x04 ) . $Append;
		$Length  = StrLen( $Command );
		if( $Length !== FWrite( $this->Socket, $Command, $Length ) )
		{
			throw new MinecraftQueryException( "Failed to write on socket." );
		}
		$Data = FRead( $this->Socket, 4096 );
		if( $Data === false )
		{
			throw new MinecraftQueryException( "Failed to read from socket." );
		}
		if( StrLen( $Data ) < 5 || $Data[ 0 ] != $Command[ 2 ] )
		{
			return false;
		}
		return SubStr( $Data, 5 );
	}
	private function ResolveSRV( &$Address, &$Port )
	{
		if( ip2long( $Address ) !== false )
		{
			return;
		}
		$Record = dns_get_record( "_minecraft._tcp." . $Address, DNS_SRV );
		if( empty( $Record ) )
		{
			return;
		}
		if( isset( $Record[ 0 ][ "target" ] ) )
		{
			$Address = $Record[ 0 ][ "target" ];
		}
	}
}
?>