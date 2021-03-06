<?php
	if(count($argv) == 3)
	{
		$num_users = (int)$argv[1];
		$num_floors = (int)$argv[2];
	}
	else
	{
		$num_users = 2;
		$num_floors = 4;
	}
?>
MODULE elevator
	VAR
		cabin_at : 0..<?= $num_floors-1 ?>;
		target : -1..<?= $num_floors-1 ?>;
	ASSIGN
		init(cabin_at) := 0;
		init(target) := -1;
	DEFINE
		has_target := target != -1;
		met_target := cabin_at = target;
		may_change_target := met_target | !has_target;
		
MODULE user
	VAR
		state : {idle, request, in_elevator};
		wants_to : 0..<?= $num_floors-1 ?>;
		user_at : 0..<?= $num_floors-1 ?>;
	ASSIGN
		init(wants_to) := 0..<?= $num_floors-1 ?>;
		init(user_at) := 0..<?= $num_floors-1 ?>;
		init(state) := idle;
		next(wants_to) := case
-- is _NOT_ part of the assignment
--			wants_to = user_at & state = idle : 0..<?= $num_floors-1 ?>;
			TRUE : wants_to;
		esac;

MODULE floor(i)
	VAR
		door_open : boolean;
	ASSIGN
		init(door_open) := FALSE;
	DEFINE
		floor := i;

MODULE controller
	VAR
		e : elevator;
<?php
for($i = 0; $i < $num_users; ++$i)
echo("		u$i : user;\n");
for($i = 0; $i < $num_floors; ++$i)
echo("		f$i : floor($i);\n");
?>
	ASSIGN

<?
for($i = 0; $i < $num_floors; ++$i)
echo("
		next(f$i.door_open) := case
			f$i.floor = e.target & e.met_target : TRUE;
			TRUE : FALSE;
		esac;
");
?>
		
		next(e.cabin_at) := case
			may_move & e.has_target & e.target < e.cabin_at : e.cabin_at - 1;
			may_move & e.has_target & e.target > e.cabin_at : e.cabin_at + 1;
			TRUE : e.cabin_at;
		esac;
		
		next(e.target) := case
<?php
for($i = 0; $i < $num_users; ++$i)
echo("			e.may_change_target & u$i.state = in_elevator : u$i.wants_to;\n");
for($i = 0; $i < $num_users; ++$i)
echo("			e.may_change_target & cabin_empty & u$i.state = request : u$i.user_at;\n");
?>
			e.may_change_target & cabin_empty & no_requests & e.cabin_at != 0 : 0;
			e.may_change_target & cabin_empty & no_requests & e.cabin_at = 0 : -1;
			TRUE : e.target;
		esac;

<?php
for($i = 0; $i < $num_users; ++$i)
echo("
		next(u$i.state) := case
			u$i.state = request & u".$i."_door_open : in_elevator;
			u$i.state = in_elevator & u$i.wants_to = u$i.user_at & u".$i."_door_open : idle;
			u$i.state = idle & u$i.user_at != u$i.wants_to : request;
			TRUE : u$i.state;
		esac;
");
?>

<?php
for($i = 0; $i < $num_users; ++$i)
echo("
		next(u$i.user_at) := case
			u$i.state = in_elevator : e.cabin_at;
			TRUE : u$i.user_at;
		esac;
");
?>

	DEFINE
		may_move := (
<?php
for($i = 0; $i < $num_floors; ++$i)
{
	echo("			(f$i.floor = e.cabin_at -> !f$i.door_open)");
	
	if($i == $num_floors-1)
		echo("\n");
	else
		echo(" &\n");
}
?>
		);
		
		cabin_empty := (
<?php
for($i = 0; $i < $num_users; ++$i)
{
	echo("			u$i.state != in_elevator");
	
	if($i == $num_users-1)
		echo("\n");
	else
		echo(" &\n");
}
?>
		);
		
		no_requests := (
<?php
for($i = 0; $i < $num_users; ++$i)
{
	echo("			u$i.state != request");
	
	if($i == $num_users-1)
		echo("\n");
	else
		echo(" &\n");
}
?>
		);
		
<?php
for($i = 0; $i < $num_users; ++$i)
{
	echo("		u".$i."_door_open := (\n");

	for($j = 0; $j < $num_floors; ++$j)
	{
		echo("			(u$i.user_at = f$j.floor -> f$j.door_open)");
	
		if($j == $num_floors-1)
			echo("\n");
		else
			echo(" &\n");
	}

	echo("		);\n");
}
?>

MODULE main
	VAR
		c : controller;
		-- The doors are safe
<?php
for($i = 0; $i < $num_floors; ++$i)
	echo("		LTLSPEC G !(c.f$i.door_open & c.e.cabin_at != $i)\n");
?>
		-- A requested floor will be served sometime.
<?php
for($i = 0; $i < $num_users; ++$i)
	for($j = 0; $j < $num_floors; ++$j)
		echo("		LTLSPEC G ((c.u$i.state = request & c.u$i.wants_to = $j) -> F (c.e.cabin_at = $j & c.f$j.door_open))\n");
?>
		-- Again and again the elevator returns to floor 0.
		LTLSPEC G F c.e.cabin_at = 0
		-- Law of nature.
<?php
for($i = 0; $i < $num_floors; ++$i)
{
	echo("		LTLSPEC G c.e.cabin_at = $i -> X (");
	for($j = $i-1; $j <= $i+1; ++$j)
		if($j >= 0 && $j < $num_floors)
		{
			echo("c.e.cabin_at = $j");
			
			if($j < $i+1 && $j < $num_floors-1)
				echo(" | ");
		}
	echo(")\n");
}
?>
		-- You can't get stuck inside.
<?php
for($i = 0; $i < $num_users; ++$i)
	echo("		LTLSPEC G c.u$i.state = in_elevator -> F c.u$i.state = idle\n");
?>
		-- The doors will eventually close
<?php
for($i = 0; $i < $num_floors; ++$i)
	echo("		LTLSPEC G c.f$i.door_open -> F !c.f$i.door_open\n");
?>
		-- An occupant has requested the elevator previously
<?php
for($i = 0; $i < $num_users; ++$i)
	echo("		LTLSPEC G c.u$i.state = in_elevator -> O c.u$i.state = request\n");
?>
		-- The target only leaves -1 if there has been done a request in the previous state.
		LTLSPEC G c.e.target != -1 -> Y (c.e.target != -1<?php for($i = 0; $i < $num_users; ++$i) echo " | c.u$i.state = request"; ?>)

		-- The doors have been safe up to now.
<?php
for($i = 0; $i < $num_floors; ++$i)
	echo("		LTLSPEC H !(c.f$i.door_open & c.e.cabin_at != $i)\n");
?>
