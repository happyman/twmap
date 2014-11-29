--
-- PostgreSQL database dump
--

-- Dumped from database version 9.3.5
-- Dumped by pg_dump version 9.3.5
-- Started on 2014-11-24 21:07:45 CST

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- TOC entry 171 (class 1259 OID 16625)
-- Name: geocoder; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE geocoder (
    id integer DEFAULT nextval('geocoder_id_seq'::regclass) NOT NULL,
    address character varying(160) NOT NULL,
    lat numeric(10,6) NOT NULL,
    lng numeric(10,6) NOT NULL,
    is_tw integer DEFAULT 0 NOT NULL,
    exact integer DEFAULT 0 NOT NULL,
    faddr text NOT NULL,
    name character varying(120) NOT NULL
);


ALTER TABLE public.geocoder OWNER TO docker;

--
-- TOC entry 172 (class 1259 OID 16637)
-- Name: map; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE map (
    mid integer DEFAULT nextval('mid_seq'::regclass) NOT NULL,
    uid integer NOT NULL,
    cdate timestamp without time zone DEFAULT now() NOT NULL,
    ddate timestamp without time zone,
    host character varying(200) NOT NULL,
    title text NOT NULL,
    "locX" integer NOT NULL,
    "locY" integer NOT NULL,
    "shiftX" integer NOT NULL,
    "shiftY" integer NOT NULL,
    "pageX" integer NOT NULL,
    "pageY" integer NOT NULL,
    filename character varying(512) NOT NULL,
    size integer NOT NULL,
    version integer NOT NULL,
    flag integer DEFAULT 0 NOT NULL,
    count bigint DEFAULT 0 NOT NULL,
    gpx integer NOT NULL,
    keepon_id integer,
    hide integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.map OWNER TO docker;

--
-- TOC entry 174 (class 1259 OID 16675)
-- Name: user; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE "user" (
    uid integer DEFAULT nextval('user_id_seq'::regclass) NOT NULL,
    email character varying(200) NOT NULL,
    type character varying(40) NOT NULL,
    name character varying(160) NOT NULL,
    "limit" integer DEFAULT 20 NOT NULL,
    cdate timestamp without time zone DEFAULT now() NOT NULL,
    login integer NOT NULL
);


ALTER TABLE public."user" OWNER TO docker;

--
-- TOC entry 1866 (class 2606 OID 16636)
-- Name: geocoder_address_key; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY geocoder
    ADD CONSTRAINT geocoder_address_key UNIQUE (address);


--
-- TOC entry 1868 (class 2606 OID 16634)
-- Name: geocoder_pkey; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY geocoder
    ADD CONSTRAINT geocoder_pkey PRIMARY KEY (id);


--
-- TOC entry 1870 (class 2606 OID 16649)
-- Name: map_keepon_id_key; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY map
    ADD CONSTRAINT map_keepon_id_key UNIQUE (keepon_id);


--
-- TOC entry 1872 (class 2606 OID 24582)
-- Name: map_mid_pk; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY map
    ADD CONSTRAINT map_mid_pk PRIMARY KEY (mid);


--
-- TOC entry 1874 (class 2606 OID 16681)
-- Name: user_pkey; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY "user"
    ADD CONSTRAINT user_pkey PRIMARY KEY (uid);


-- Completed on 2014-11-24 21:07:47 CST

--
-- PostgreSQL database dump complete
--

