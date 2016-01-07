--
-- PostgreSQL database dump
--

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
-- Name: point2; Type: TABLE; Schema: public; Owner: docker; Tablespace: 
--

CREATE TABLE point2 (
    id integer DEFAULT nextval('point_id_seq'::regclass) NOT NULL,
    name character varying(100) NOT NULL,
    alias character varying(100) DEFAULT NULL::character varying,
    coord geometry(Point,4326),
    type ptype,
    class point_class NOT NULL,
    number integer,
    status rock_status,
    ele integer,
    mt100 integer DEFAULT 0 NOT NULL,
    checked integer DEFAULT 0 NOT NULL,
    comment text,
    owner integer DEFAULT 0 NOT NULL,
    contribute integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.point2 OWNER TO docker;

--
-- Name: point2_name_key; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY point2
    ADD CONSTRAINT point2_name_key UNIQUE (name);


--
-- Name: point2_pkey; Type: CONSTRAINT; Schema: public; Owner: docker; Tablespace: 
--

ALTER TABLE ONLY point2
    ADD CONSTRAINT point2_pkey PRIMARY KEY (id);


--
-- PostgreSQL database dump complete
--

