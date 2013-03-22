-- phpMyAdmin SQL Dump
-- version 3.3.9
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Feb 06, 2012 at 05:18 PM
-- Server version: 5.1.53
-- PHP Version: 5.3.4

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Database: `33dashboard`
--

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `twitter`, `last_sign_in`, `user_level`, `token_id`) VALUES
(1, 'admin', 'info@outlandishideas.co.uk', 'a0c59119e6ba0a1a7bcd0110e13ea937a34f036b', 'outlandishideas', '2012-02-06 15:45:56', 10, NULL);
